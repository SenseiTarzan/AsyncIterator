<?php

declare(strict_types=1);

namespace muqsit\asynciterator\handler;

use Closure;
use Exception;
use Generator;
use Iterator;
use muqsit\asynciterator\util\EmptyTimedClosure;
use muqsit\asynciterator\util\KeyValueTimedClosure;
use SOFe\AwaitGenerator\Await;

/**
 * @template TKey
 * @template TValue
 * @implements AsyncForeachHandler<TKey, TValue>
 */
final class SimpleAsyncForeachHandlerAwait implements AsyncForeachHandler{

	private const COMPLETION_CALLBACKS = 0;
	private const INTERRUPTION_CALLBACKS = 1;
	private const EMPTY_CALLBACKS = 2;

	private string $timings_parent_name;

	/** @var array<KeyValueTimedClosure<TKey, TValue, AsyncForeachResult|Generator<AsyncForeachResult>>> */
	private array $callbacks = [];

	private int $finalization_type = self::COMPLETION_CALLBACKS;

	/** @var array<int, array<EmptyTimedClosure>>  */
	private array $finalization_callbacks = [
		self::COMPLETION_CALLBACKS => [],
		self::INTERRUPTION_CALLBACKS => [],
		self::EMPTY_CALLBACKS => []
	];

	/**
	 * @param Iterator<TKey, TValue> $iterable
	 * @param int $entries_per_tick
	 */
	public function __construct(
		readonly private Iterator $iterable,
		readonly private int $entries_per_tick
	){
		$iterable->rewind();
	}

	public function init(string $timings_parent_name) : void{
		$this->timings_parent_name = $timings_parent_name;
	}

	public function interrupt() : void{
		$this->cancelNext();
		$this->finalization_type = self::INTERRUPTION_CALLBACKS;
	}

	public function cancel() : void{
		$this->cancelNext();
		$this->finalization_type = self::EMPTY_CALLBACKS;
	}

	private function cancelNext() : void{
		$this->callbacks = [];
		$this->as(static function() : Generator{ return yield from AsyncForeachResult::CANCEL(); });
	}

	public function handle() : Generator
    {
		return Await::promise(function ($resolve): void{
            Await::f2c(function (): Generator{
                $per_run = $this->entries_per_tick;
                while($this->iterable->valid()){
                    /** @var TKey $key */
                    $key = $this->iterable->key();

                    /** @var TValue $value */
                    $value = $this->iterable->current();

                    foreach($this->callbacks as $callback){
                        $tt = $callback->call($key, $value);
                        if ($tt instanceof Generator){
                            if(!((yield from $tt)->handle($this))){
                                return false;
                            }
                        }else if($tt instanceof AsyncForeachResult && !$tt->handle($this)){
                            return false;
                        }
                    }

                    $this->iterable->next();
                    if(--$per_run === 0){
                        return true;
                    }
                }
                return false;
            }, $resolve);
        });
	}

	public function doCompletion() : void{
		foreach($this->finalization_callbacks[$this->finalization_type] as $callback){
			$callback->call();
		}
	}

	public function as(Closure $callback) : AsyncForeachHandler
    {
		$this->callbacks[spl_object_id($callback)] = new KeyValueTimedClosure(AsyncForeachHandlerTimings::getTraverserTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onCompletion(Closure $callback) : AsyncForeachHandler
    {
		$this->finalization_callbacks[self::COMPLETION_CALLBACKS][spl_object_id($callback)] = new EmptyTimedClosure(AsyncForeachHandlerTimings::getOnCompletionTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onInterruption(Closure $callback) : AsyncForeachHandler{
		$this->finalization_callbacks[self::INTERRUPTION_CALLBACKS][spl_object_id($callback)] = new EmptyTimedClosure(AsyncForeachHandlerTimings::getOnInterruptionTimings($this->timings_parent_name, $callback), $callback);
		return $this;
	}

	public function onCompletionOrInterruption(Closure $callback) : AsyncForeachHandler{
		return $this->onCompletion($callback)
			->onInterruption($callback);
	}
}
