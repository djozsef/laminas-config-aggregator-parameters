<?php
namespace Laminas\ConfigAggregatorParameters;

use Laminas\ConfigAggregatorParameters\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException as SymfonyParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;


class SelfInjectingPostProcessor
{
    /**
     * @var bool
     */
    private $continueOnError;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @param bool $continueOnError
     */
    public function __construct(?bool $continueOnError = true)
    {
        $this->continueOnError = $continueOnError;
    }

    public function __invoke(array $config): array
    {
        $this->parameters = $config;
        try {
            $parameters = $this->getResolvedParameters();
        } catch (SymfonyParameterNotFoundException $exception) {
            if ($this->continueOnError !== true) {
                throw ParameterNotFoundException::fromException($exception);
            }
        }
        array_walk_recursive($config, function (&$value) use ($parameters) {
            try {
                $value = $parameters->unescapeValue($parameters->resolveValue($value));
            } catch (SymfonyParameterNotFoundException $exception) {
                if ($this->continueOnError !== true) {
                    throw ParameterNotFoundException::fromException($exception);
                }
            }
        });

        $config['parameters'] = $parameters->all();

        return $config;
    }

    private function resolveNestedParameters(array $values, string $prefix = ''): array
    {
        $convertedValues = [];
        foreach ($values as $key => $value) {
            // Do not provide numeric keys as single parameter
            if (is_numeric($key)) {
                continue;
            }

            $convertedValues[$prefix . $key] = $value;
            if (is_array($value)) {
                $convertedValues += $this->resolveNestedParameters($value, $prefix . $key . '.');
            }
        }

        return $convertedValues;
    }

    private function getResolvedParameters(): ParameterBag
    {
        $resolved = $this->resolveNestedParameters($this->parameters);
        $bag = new ParameterBag($resolved);

        $bag->resolve();
        return $bag;
    }
}