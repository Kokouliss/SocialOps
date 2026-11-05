<?php

namespace App\Bio;

use App\Models\Client;
use Illuminate\Support\Str;

/** The buttons of a client's link-in-bio page: the profile placeholders that hold a link, a phone number or an e-mail, then the social accounts. */
final class BioLinks
{
    private const LABELS = ['booking' => 'Book now', 'book' => 'Book now', 'site' => 'Website', 'website' => 'Website', 'menu' => 'Menu', 'maps' => 'Directions', 'directions' => 'Directions', 'phone' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];

    /** @return list<array{label: string, url: string, kind: string}> kind is link, phone, email or social */
    public static function for(Client $client): array
    {
        $links = [];
        foreach ((array) ($client->profile['fields'] ?? []) as $name => $value) {
            $value = trim((string) $value);
            $label = __(self::LABELS[$name] ?? Str::ucfirst(str_replace('_', ' ', (string) $name)));
            $phone = preg_match('/^\+?[\d\s]{6,}$/', $value);
            if ($name === 'whatsapp' && $phone) {
                $links[] = ['label' => $label, 'url' => 'https://wa.me/'.preg_replace('/\D/', '', $value), 'kind' => 'link'];
            } elseif ($phone) {
                $links[] = ['label' => $label, 'url' => 'tel:'.preg_replace('/\s/', '', $value), 'kind' => 'phone'];
            } elseif (preg_match('~^https?://\S+$~i', $value)) {
                $links[] = ['label' => $label, 'url' => $value, 'kind' => 'link'];
            } elseif (preg_match('~^[\w-]+(\.[\w-]+)+(/\S*)?$~', $value)) { // a bare domain such as almyra.gr
                $links[] = ['label' => $label, 'url' => 'https://'.$value, 'kind' => 'link'];
            } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $links[] = ['label' => $label, 'url' => 'mailto:'.$value, 'kind' => 'email'];
            }
        }
        $accounts = (array) ($client->profile['accounts'] ?? []);
        if ($handle = ltrim(trim((string) ($accounts['instagram'] ?? '')), '@')) {
            $links[] = ['label' => 'Instagram', 'url' => 'https://www.instagram.com/'.$handle, 'kind' => 'social'];
        }
        if ($handle = ltrim(trim((string) ($accounts['tiktok'] ?? '')), '@')) {
            $links[] = ['label' => 'TikTok', 'url' => 'https://www.tiktok.com/@'.$handle, 'kind' => 'social'];
        }
        if ($page = $client->accounts->firstWhere('platform', 'facebook')?->external_id) {
            $links[] = ['label' => 'Facebook', 'url' => 'https://www.facebook.com/'.$page, 'kind' => 'social'];
        }

        return $links;
    }
}
