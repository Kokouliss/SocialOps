<?php

namespace App\Http\Controllers;

use Dotenv\Dotenv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** The server configuration (.env) edited in the browser by an admin, so nobody has to open system files. */
class SettingsController extends Controller
{
    /** A new key makes every encrypted token and signed link unreadable, so it stays out of reach. */
    private const LOCKED = ['APP_KEY'];

    public function edit(): View
    {
        $path = app()->environmentFilePath();

        return view('settings.index', ['values' => is_file($path) ? Dotenv::parse(file_get_contents($path)) : []]);
    }

    /** One short mail to the admin with the current mail settings: the server's own answer on the page when it fails. */
    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::raw(__('This is a test e-mail from Social Ops. If you can read it, the mail settings work.'), fn ($message) => $message->to($request->user()->email)->subject(__('Social Ops test e-mail')));
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['mail' => __('The test e-mail could not be sent: :error', ['error' => $e->getMessage()])]);
        }

        return back()->with('status', 'test-mail-sent');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['values' => ['array'], 'values.*' => ['nullable', 'string', 'max:4000'], 'new_name' => ['nullable', 'regex:/^[A-Z][A-Z0-9_]*$/'], 'new_value' => ['nullable', 'string', 'max:4000']]);
        $values = array_map(fn ($value) => (string) $value, $data['values'] ?? []);
        if ($data['new_name'] ?? '') {
            $values[$data['new_name']] = (string) ($data['new_value'] ?? '');
        }
        $values = array_filter($values, fn ($value, $name) => preg_match('/^[A-Z][A-Z0-9_]*$/', $name) && ! in_array($name, self::LOCKED), ARRAY_FILTER_USE_BOTH);

        $path = app()->environmentFilePath();
        if (! is_file($path) || ! is_writable($path) || ! is_writable(dirname($path))) {
            return back()->withErrors(['settings' => __('The .env file is not writable by the web server.')]);
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as &$line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $values)) {
                $line = $m[1].'='.$this->quote($values[$m[1]]);
                unset($values[$m[1]]);
            }
        }
        unset($line);
        foreach ($values as $name => $value) {
            $lines[] = $name.'='.$this->quote($value);
        }
        copy($path, $path.'.backup'); // the previous file, for a rollback by hand when a value locks the app out
        file_put_contents($path.'.tmp', implode("\n", $lines)."\n");
        rename($path.'.tmp', $path);
        Artisan::call('config:cache'); // the next request and the next queue minute run on the new values

        return redirect()->route('settings.edit')->with('status', 'settings-saved');
    }

    private function quote(string $value): string
    {
        return preg_match('~^[\w./:@,+-]*$~', $value) ? $value : '"'.addcslashes($value, '"\\').'"';
    }
}
