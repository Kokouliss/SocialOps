<?php

namespace App\Publishing;

use RuntimeException;

/** Something no API can do: the post goes to Manual-Needed and a human posts it in the app. */
class ManualNeeded extends RuntimeException {}
