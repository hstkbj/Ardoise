<?php

namespace App\Services\Imports;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Import refusé : la liste des lignes en erreur est renvoyée, rien n'est enregistré. */
class ImportException extends RuntimeException
{
    /** @param  array<int, array{line: int, message: string}>  $lines */
    public function __construct(public readonly array $lines)
    {
        parent::__construct(count($lines).' ligne(s) en erreur : corrigez le fichier puis renvoyez-le. Aucune donnée n’a été enregistrée.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['file' => [$this->getMessage()]],
            'lines' => array_slice($this->lines, 0, 200),
        ], 422);
    }
}
