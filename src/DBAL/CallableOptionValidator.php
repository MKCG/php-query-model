<?php

namespace MKCG\Model\DBAL;

final class CallableOptionValidator
{
    private $tokens = [];
    private $paramTokens = [];
    private $firstParamLine;
    private $lastParamPos;
    private $errors = [];

    private function __construct(
        callable $callback,
        int $minParamExpected,
        int $maxParamExpected,
        bool $expectReturn
    ) {
        if ($minParamExpected < 0
            && $maxParamExpected < 0
            && $expectReturn === false
        ) {
            return;
        }

        $this->tokenize($callback);
        $this->tokenizeParameters();

        if ($minParamExpected >= 0 || $maxParamExpected >= 0) {
            $paramCount = count($this->paramTokens);

            if ($minParamExpected >= 0 && $paramCount < $minParamExpected) {
                $this->errors[] = sprintf(
                    'callable must have at least %d arguments, found : %d',
                    $minParamExpected,
                    $paramCount
                );
            } else if ($maxParamExpected >= 0 && $paramCount > $maxParamExpected && !$this->lastParamIsVariadic()) {
                $this->errors[] = sprintf(
                    'callable must have at most %d arguments, found : %d',
                    $maxParamExpected,
                    $paramCount
                );
            }
        }

        if ($expectReturn && !$this->alwaysReturnValues()) {
            $this->errors[] = 'callable must always return a value';
        }
    }

    public static function validateCallable(
        callable $callback,
        int $minParamExpected,
        int $maxParamExpected,
        bool $expectReturn
    ) {
        if ($maxParamExpected < $minParamExpected) {
            throw new \LogicException("$maxParamExpected must be greater than or equal to $minParamExpected");
        }

        return new self($callback, $minParamExpected, $minParamExpected, $expectReturn);
    }

    public function isValid() : bool
    {
        return $this->errors === [];
    }

    public function getErrors() : array
    {
        return $this->errors;
    }

    private function alwaysReturnValues() : bool
    {
        $count = count($this->tokens);

        $isReturn = false;
        $returns = [];
        $currentTokens = [];

        for ($i = $this->lastParamPos + 1; $i < $count; $i++) {
            if ($isReturn === false) {
                $isReturn = is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_RETURN;
                continue;
            }

            if ($this->tokens[$i] === ';') {
                $isReturn = false;
                $returns[] = $currentTokens;
                $currentTokens = [];
                continue;
            }

            $currentTokens[] = $this->tokens[$i];
        }

        if ($currentTokens !== []) {
            $returns[] = $currentTokens;
        }

        $returns = array_map(function(array $tokens) {
            $tokens = array_filter($tokens, function($token) {
                return !is_array($token) || $token[0] !== T_WHITESPACE;
            });

            return !empty($tokens);
        }, $returns);

        return array_reduce($returns, function($acc, $val) {
            return $acc !== false && $val !== false;
        }, null);
    }

    private function tokenize(callable $callback)
    {
        $reflection = new \ReflectionFunction($callback);

        $fromLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        $state = 0;

        foreach (token_get_all(file_get_contents($reflection->getFileName())) as $token) {
            if ($state === 0) {
                if (is_array($token) && $token[2] >= $fromLine) {
                    $state = 1;
                } else {
                    continue;
                }
            }

            if ($state === 1 && is_array($token)){
                if ($token[0] === T_FUNCTION
                    || (defined('T_FN') && $token[0] === T_FN)
                ) {
                    $state = 2;
                }
            }

            if ($state === 2) {
                if (is_array($token) && $token[2] > $endLine) {
                    break;
                }

                $this->tokens[] = $token;
            }
        }
    }

    private function tokenizeParameters()
    {
        $this->paramTokens = [];
        $currentParamTokens = [];
        $state = 0;

        foreach ($this->tokens as $pos => $token) {
            if ($token === '(') {
                $state++;
            } else if ($token === ')') {
                if (--$state === 0) {
                    if ($currentParamTokens !== []) {
                        $this->paramTokens[] = $currentParamTokens;
                    }

                    $this->lastParamPos = $pos;
                    break;
                }
            } else if ($state > 0) {
                if ($state !== 1 || $token !== ',') {
                    $currentParamTokens[] = $token;

                    if (is_array($token) && $this->firstParamLine === null) {
                        $this->firstParamLine = $token[2];
                    }
                } else {
                    $this->paramTokens[] = $currentParamTokens;
                    $currentParamTokens = [];
                }
            }
        }
    }

    private function lastParamIsVariadic() : bool
    {
        if (!isset($this->paramTokens[0])) {
            return false;
        }

        $param = $this->paramTokens[count($this->paramTokens) - 1];

        foreach ($param as $token) {
            if (is_array($token) && $token[0] === T_ELLIPSIS) {
                return true;
            }
        }

        return false;
    }
}
