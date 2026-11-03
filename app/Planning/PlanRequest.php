<?php

namespace App\Planning;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PlanRequest
{
    /** Each post costs a caption call, so a batch stays small enough to review in one sitting. */
    public const MAX_POSTS = 30;

    /** @param  array  $profile  the client profile, shaped like config/clients/example.yaml (Client::toProfileArray()) */
    public function __construct(
        public readonly array $profile,
        public readonly int $count,
        public readonly array $platforms,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
        if ($count < 1 || $count > self::MAX_POSTS) {
            throw new InvalidArgumentException('Posts per batch must be between 1 and '.self::MAX_POSTS);
        }
        if ($platforms === [] || array_diff($platforms, self::accounts($profile)) !== []) {
            throw new InvalidArgumentException('Platforms must be a non-empty subset of the client accounts: '.implode(', ', self::accounts($profile)));
        }
        if ($to->lt($from)) {
            throw new InvalidArgumentException('The publishing window ends before it starts');
        }
    }

    /** Platforms with an account in the profile, in profile order. */
    public static function accounts(array $profile): array
    {
        return array_keys(array_filter($profile['accounts'] ?? []));
    }

    public function promptVars(): array
    {
        return Prompt::profileVars($this->profile) + [
            'today' => CarbonImmutable::today()->toDateString(),
            'window' => ['start' => $this->from->toDateString(), 'end' => $this->to->toDateString()],
            'platforms' => $this->platforms,
            'count' => $this->count,
            'holidays' => Holidays::lines($this->from, $this->to, Holidays::inCyprus($this->profile['location'] ?? null)),
        ];
    }
}
