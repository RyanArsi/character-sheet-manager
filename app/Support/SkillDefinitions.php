<?php

namespace App\Support;

class SkillDefinitions
{
    const ALL = [
        // Perícias gerais
        ['name' => 'Percepção',          'attribute' => 'sab', 'category' => 'pericia'],
        ['name' => 'Sensitivo',          'attribute' => 'sab', 'category' => 'pericia'],
        ['name' => 'Controle de Chakra', 'attribute' => 'int', 'category' => 'pericia'],
        ['name' => 'Selamento',          'attribute' => 'nin', 'category' => 'pericia'],
        ['name' => 'Invocação',          'attribute' => 'nin', 'category' => 'pericia'],
        ['name' => 'Medicina',           'attribute' => 'int', 'category' => 'pericia'],
        ['name' => 'Furtividade',        'attribute' => 'agi', 'category' => 'pericia'],
        ['name' => 'Prestidigitação',    'attribute' => 'agi', 'category' => 'pericia'],
        ['name' => 'Rastreamento',       'attribute' => 'sab', 'category' => 'pericia'],
        ['name' => 'Sobrevivência',      'attribute' => 'int', 'category' => 'pericia'],
        ['name' => 'Acrobacia',          'attribute' => 'tai', 'category' => 'pericia'],
        ['name' => 'Persuasão',          'attribute' => 'car', 'category' => 'pericia'],
        ['name' => 'Enganação',          'attribute' => 'car', 'category' => 'pericia'],
        ['name' => 'Conhecimento',       'attribute' => 'int', 'category' => 'pericia'],
        ['name' => 'Intimidação',        'attribute' => 'car', 'category' => 'pericia'],

        // Resistências
        ['name' => 'Reflexos',  'attribute' => 'agi/tai', 'category' => 'resistencia'],
        ['name' => 'Fortitude', 'attribute' => 'con',     'category' => 'resistencia'],
        ['name' => 'Vontade',   'attribute' => 'sab',     'category' => 'resistencia'],

        // Combate
        ['name' => 'Daken-jutsu',     'attribute' => 'tai', 'category' => 'combate'],
        ['name' => 'Bukijutsu',       'attribute' => 'tai', 'category' => 'combate'],
        ['name' => 'Shurikenjutsu',   'attribute' => 'tai', 'category' => 'combate'],
        ['name' => 'Ataque-ninjutsu', 'attribute' => 'nin', 'category' => 'combate'],
    ];
}
