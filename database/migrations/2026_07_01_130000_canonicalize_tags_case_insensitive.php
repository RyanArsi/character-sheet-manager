<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Normaliza todas as tags existentes para o novo padrão: identidade
 * case-insensitive, primeira letra sempre maiúscula, primeira grafia vence.
 * Funde duplicatas do dicionário que diferem só na caixa e reescreve os
 * arrays `tags` de jutsus/talents/equipments/actions para a grafia canônica.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Dicionário: menor id de cada chave (lower) vence; força inicial maiúscula.
        $survivorByKey = [];   // key => row sobrevivente (menor id)
        $canonical     = [];   // key => nome canônico (display)

        foreach (DB::table('tags')->orderBy('id')->get() as $row) {
            $display = Str::ucfirst(trim($row->name));
            $key     = Str::lower($display);

            if (! isset($survivorByKey[$key])) {
                $survivorByKey[$key] = $row;
                $canonical[$key]     = $display;
            } elseif (! empty($row->description) && empty($survivorByKey[$key]->description)) {
                // Duplicado case-insensitive: herda a descrição se o sobrevivente não tiver.
                $survivorByKey[$key]->description = $row->description;
            }
        }

        // Remove os duplicados antes de renomear os sobreviventes (evita colisão de nome).
        $survivorIds = array_map(fn ($r) => $r->id, $survivorByKey);
        if ($survivorIds) {
            DB::table('tags')->whereNotIn('id', $survivorIds)->delete();
        }

        foreach ($survivorByKey as $key => $row) {
            DB::table('tags')->where('id', $row->id)->update([
                'name'        => $canonical[$key],
                'description' => $row->description,
            ]);
        }

        // 2. Arrays `tags` dos itens: mapeia p/ a grafia canônica, dedup case-insensitive.
        foreach (['jutsus', 'talents', 'equipments', 'actions'] as $table) {
            foreach (DB::table($table)->select('id', 'tags')->get() as $item) {
                $tags = json_decode($item->tags ?? '[]', true);
                if (! is_array($tags)) {
                    continue;
                }

                $new  = [];
                $seen = [];
                foreach ($tags as $t) {
                    $display = Str::ucfirst(trim(str_replace(['"', "'"], '', (string) $t)));
                    if ($display === '') {
                        continue;
                    }
                    $key = Str::lower($display);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $new[] = $canonical[$key] ?? $display;
                }

                if ($new !== $tags) {
                    DB::table($table)->where('id', $item->id)->update([
                        'tags' => json_encode($new),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Transformação de dados irreversível (a caixa original é perdida).
    }
};
