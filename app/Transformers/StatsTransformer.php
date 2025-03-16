<?php

namespace App\Transformers;

use Carbon\Carbon;

class StatsTransformer
{
    private array $stats = [];

    public static function create(): self
    {
        return new self();
    }

    public static function from(?array $existingStats = null): self
    {
        $instance = new self();
        $instance->stats = $existingStats ?? $instance->initialize()->toArray();

        return $instance;
    }

    public function initialize(?string $now = null): self
    {
        $now = $now ?? Carbon::now()->toIso8601String();

        $this->stats = [
            'search' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'total' => 0],
            'follow' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'total' => 0],
            'like' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'total' => 0],
            'comment' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'total' => 0],
            'view' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0, 'total' => 0],
            'last_reset' => $now,
            'flags' => [
                'is_temp_banned' => false,
                'warned_for_follow' => false,
                'warned_for_like' => false,
            ],
            'metrics' => [
                'failure_reasons' => [
                    'network' => 0,
                    'rate_limit' => 0,
                    'not_found' => 0,
                ],
            ],
            'activities' => [
                'last_relogin' => $now,
                'last_login' => $now,
                'last_search' => null,
                'last_follow' => null,
                'last_like' => null,
                'last_comment' => null,
                'last_change_password' => null,
            ],
        ];

        return $this;
    }

    public function withActivity(string $activityType, ?string $timestamp = null): self
    {
        return $this->set("activities.{$activityType}", $timestamp ?? Carbon::now()->toIso8601String());
    }

    /**
     * Set a value in the stats array using dot notation
     *
     * @param  string  $path  Dot notation path (e.g. 'activities.last_login')
     * @param  mixed  $value  The value to set
     */
    public function set(string $path, $value): self
    {
        \Illuminate\Support\Arr::set($this->stats, $path, $value);

        return $this;
    }

    /**
     * Get a value from the stats array using dot notation
     *
     * @param  string  $path  Dot notation path
     * @param  mixed  $default  Default value if path doesn't exist
     * @return mixed
     */
    public function get(string $path, $default = null)
    {
        return \Illuminate\Support\Arr::get($this->stats, $path, $default);
    }

    public function incrementTaskCount(string $task, string $period = 'daily'): self
    {
        if (isset($this->stats[$task][$period])) {
            $this->stats[$task][$period]++;

            // Make sure 'total' key exists before incrementing
            if (! isset($this->stats[$task]['total'])) {
                $this->stats[$task]['total'] = 0;
            }

            $this->stats[$task]['total']++;
        }

        return $this;
    }

    public function setFlag(string $flagName, bool $value): self
    {
        if (isset($this->stats['flags'][$flagName])) {
            $this->stats['flags'][$flagName] = $value;
        }

        return $this;
    }

    public function incrementFailureReason(string $reason): self
    {
        if (isset($this->stats['metrics']['failure_reasons'][$reason])) {
            $this->stats['metrics']['failure_reasons'][$reason]++;
        }

        return $this;
    }

    public function toStatement()
    {
        return json_encode($this->stats);
    }

    public function toArray(): array
    {
        return $this->stats;
    }
}
