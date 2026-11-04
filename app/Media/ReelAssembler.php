<?php

namespace App\Media;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** Cuts venue clips into one vertical Reel with ffmpeg: each clip trimmed, scaled and cropped to 9:16, concatenated, an optional headline over the first seconds, no sound (music is added in the app). */
final class ReelAssembler
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    public const MAX_CLIPS = 10;

    public const HEADLINE_SECONDS = 3;

    private const FONT = 'resources/fonts/InterTight-Bold.ttf';

    /** The ffmpeg binary, or null when the server has none. */
    public static function binary(): ?string
    {
        $path = (string) config('services.ffmpeg.path', 'ffmpeg');

        return str_contains($path, '/') ? (is_executable($path) ? $path : null) : (new ExecutableFinder)->find($path);
    }

    /**
     * @param  list<string>  $clips  paths of the clips, in playing order
     * @param  int  $seconds  what each clip is cut to (a shorter clip plays whole)
     */
    public function assemble(array $clips, int $seconds, string $headline, string $out): void
    {
        $binary = self::binary() ?? throw new RuntimeException('ffmpeg is not installed on the server (docs/pilot-setup.md).');
        $command = [$binary, '-y', '-hide_banner', '-loglevel', 'error'];
        $filters = [];
        foreach ($clips as $i => $clip) {
            array_push($command, '-t', (string) $seconds, '-i', $clip);
            $filters[] = sprintf('[%d:v]scale=%d:%d:force_original_aspect_ratio=increase,crop=%d:%d,setsar=1,fps=30,setpts=PTS-STARTPTS[v%d]', $i, self::WIDTH, self::HEIGHT, self::WIDTH, self::HEIGHT, $i);
        }
        $filters[] = implode('', array_map(fn (int $i) => "[v{$i}]", array_keys($clips))).'concat=n='.count($clips).':v=1:a=0[reel]';
        $textFile = null;
        if (trim($headline) !== '') {
            // drawtext takes the text from a file, so quotes and colons in the headline need no escaping; the lines are wrapped with GD like the photo overlay
            $size = (int) (self::WIDTH * 0.06);
            file_put_contents($textFile = tempnam(sys_get_temp_dir(), 'headline'), implode("\n", TextOverlay::wrap($headline, base_path(self::FONT), $size, (int) (self::WIDTH * 0.84))));
            $filters[] = sprintf("[reel]drawtext=fontfile=%s:textfile=%s:fontsize=%d:fontcolor=white:line_spacing=%d:x=(w-text_w)/2:y=h*0.72:box=1:boxcolor=black@0.55:boxborderw=%d:enable='lt(t,%d)'[reel]", base_path(self::FONT), $textFile, $size, (int) ($size / 3), (int) ($size / 2), self::HEADLINE_SECONDS);
        }
        array_push($command, '-filter_complex', implode(';', $filters), '-map', '[reel]', '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', '-f', 'mp4', $out);
        $process = (new Process($command))->setTimeout(840);
        $process->run();
        if ($textFile) {
            @unlink($textFile);
        }
        throw_unless($process->isSuccessful(), new RuntimeException('ffmpeg: '.trim($process->getErrorOutput() ?: $process->getOutput())));
    }
}
