<?php

namespace App\Http\Controllers;

use App\Bio\BioLinks;
use App\Models\Client;
use Illuminate\View\View;

/** The public link-in-bio page of a client, the address that goes in the Instagram and TikTok profile. */
class BioController extends Controller
{
    public function __invoke(Client $client): View
    {
        return view('bio', ['client' => $client, 'links' => BioLinks::for($client)]);
    }
}
