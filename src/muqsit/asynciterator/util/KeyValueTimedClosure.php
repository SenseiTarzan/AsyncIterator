<?php

declare(strict_types=1);

namespace muqsit\asynciterator\util;

use Closure;
use pocketmine\timings\TimingsHandler;

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
		$return = $await ? yield from ($this->closure)($key, $value) : $await;
		$this->timings->stopTiming();
		return $return;
	}
}