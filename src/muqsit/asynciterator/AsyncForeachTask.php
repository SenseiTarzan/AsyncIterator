<?php

declare(strict_types=1);

namespace muqsit\asynciterator;

use muqsit\asynciterator\handler\AsyncForeachHandler;
use muqsit\asynciterator\handler\SimpleAsyncForeachHandlerAwait;
use pocketmine\scheduler\Task;
use SOFe\AwaitGenerator\Await;

/**
 * @template TKey
 * @template TValue
 */
final class AsyncForeachTask extends Task{

	/**
	 * @param AsyncForeachHandler<TKey, TValue> $async_foreach_handler
	 */
	public function __construct(
		readonly private AsyncForeachHandler $async_foreach_handler
	){}

	public function onRun() : void{
        if ($this->async_foreach_handler instanceof SimpleAsyncForeachHandlerAwait){
                Await::f2c(function (){
                    return yield from $this->async_foreach_handler->handle();
                }, function (bool $value): void {
                    if (!$value){
                        $this->async_foreach_handler->doCompletion();
                        $task_handler = $this->getHandler();
                        if($task_handler !== null){
                            $task_handler->cancel();
                        }
                    }
                });
                return;
        }
		if(!$this->async_foreach_handler->handle()){
			$this->async_foreach_handler->doCompletion();
			$task_handler = $this->getHandler();
			if($task_handler !== null){
				$task_handler->cancel();
			}
		}
	}
}