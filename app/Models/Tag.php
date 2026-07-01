<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'description'];

    /**
     * Grafia de exibição de uma tag: limpa aspas/espaços e força a primeira
     * letra maiúscula, mantendo o resto como foi escrito. Retorna null se vazia.
     * Não toca no banco — é só a forma "crua" antes de resolver o dicionário.
     */
    public static function displayName(string $raw): ?string
    {
        $clean = trim(str_replace(['"', "'"], '', $raw));
        if ($clean === '') {
            return null;
        }

        return Str::ucfirst($clean);
    }

    /**
     * Nome canônico de uma tag. A identidade é case-insensitive: se já existir
     * uma tag com o mesmo nome ignorando a caixa, a primeira grafia salva vence
     * (não sobrescreve). Caso contrário, cria a entrada no dicionário com a
     * primeira letra maiúscula. Retorna null para entradas vazias.
     */
    public static function canonicalName(string $raw): ?string
    {
        $name = static::displayName($raw);
        if ($name === null) {
            return null;
        }

        $existing = static::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
        if ($existing) {
            return $existing->name;
        }

        return static::create(['name' => $name])->name;
    }

    /**
     * Canonicaliza e deduplica (case-insensitive, preservando a ordem) uma lista
     * de tags cruas, garantindo cada uma no dicionário.
     *
     * @param  iterable<mixed>  $raw
     * @return array<int,string>
     */
    public static function canonicalList(iterable $raw): array
    {
        $out = [];
        $seen = [];

        foreach ($raw as $t) {
            $name = static::canonicalName((string) $t);
            if ($name === null) {
                continue;
            }

            $key = Str::lower($name);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $name;
        }

        return $out;
    }
}
