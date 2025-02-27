<?php

declare(strict_types=1);

namespace muqsit\asynciterator\util;

use Closure;
use pocketmine\timings\TimingsHandler;
use SOFe\AwaitGenerator\Await;

/**
 * @template T
 * @template U
 * @template V
 */
final class KeyValueTimedClosure{

	/**
	 * @param TimingsHandler $timings
	 * @param Closure(T, U) : V $closure
	 */
	public function __construct(
		readonly private TimingsHandler $timings,
		readonly public Closure $closure
	){}

	/**
	 * @param T $key
	 * @param U $value
	 * @return V
	 */
	public function call(mixed $key, mixed $value, bool $await = false) : mixed{
        $this->timings->startTiming();
        $return = ($this->closure)($key, $value);
        $this->timings->stopTiming();
		return $return;
	}
}