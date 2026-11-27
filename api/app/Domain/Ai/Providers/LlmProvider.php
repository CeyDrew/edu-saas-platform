<?php

namespace App\Domain\Ai\Providers;

/**
 * A language model that answers with JSON matching a schema (structured output).
 */
interface LlmProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @param  array<string, mixed>  $schema  JSON schema the answer must follow.
     * @return string The raw JSON text returned by the model (validated by the caller).
     *
     * @throws LlmException
     */
    public function generateJson(string $system, string $prompt, array $schema): string;
}
