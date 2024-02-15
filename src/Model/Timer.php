<?php

namespace Charcoal\Conductor\Model;

final class Timer
{
    private float $start_time = 0;
    private float $stop_time = 0;

    public function __construct()
    {
    }

    public function start(): self
    {
        $this->reset();
        $this->start_time = microtime(true);

        return $this;
    }

    public function stop(): string
    {
        $this->stop_time = microtime(true);
        $timediff = $this->stop_time - $this->start_time;

        return $this->formatTime($timediff);
    }

    public function reset(): self
    {
        $this->start_time = 0;
        $this->stop_time = 0;

        return $this;
    }

    private function formatTime(float $seconds): string
    {
        $time = number_format($seconds, 2);
        return $time;
    }
}
