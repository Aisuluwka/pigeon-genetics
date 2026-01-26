<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/**
 * Демо-лаборатория генетики голубей.
 * Локус S/s: Spread (доминантный S_) — тёмный окрас.
 * Локус D/d: Dilute (только dd) — осветляет.
 * Локус P/p: Piebald (доминантный P_) — белые пятна.
 *
 * Режим: 2 локуса (S,D) -> 4x4; 3 локуса (S,D,P) -> 8x8.
 * Без БД и NPM — одна страница (Blade).
 */
Route::match(['get','post'], '/', function (Request $r) {

    // ===== helpers =====

    // Нормализация пары аллелей: всегда заглавная буква первая (Ss, Dd, Pp)
    $normalizePair = function(string $pair): string {
        $a = $pair[0] ?? '';
        $b = $pair[1] ?? '';
        if ($a === '' || $b === '') return $pair;

        // если первый строчный, а второй заглавный -> меняем местами
        if (ctype_lower($a) && ctype_upper($b)) {
            return $b.$a; // sS -> Ss
        }

        // иначе оставляем как есть (Ss, SS, ss)
        return $a.$b;
    };

    // Разбор генотипа на 2 или 3 локуса.
    $parseGenoN = function(string $g, int $loci) use ($normalizePair): ?array {
        $g = trim($g);

        if ($loci === 2) {
            if (!preg_match('/^[Ss][Ss][Dd][Dd]$/', $g)) return null;
            return [
                'S' => $normalizePair($g[0].$g[1]),
                'D' => $normalizePair($g[2].$g[3]),
            ];
        } else { // 3 локуса
            if (!preg_match('/^[Ss][Ss][Dd][Dd][Pp][Pp]$/', $g)) return null;
            return [
                'S' => $normalizePair($g[0].$g[1]),
                'D' => $normalizePair($g[2].$g[3]),
                'P' => $normalizePair($g[4].$g[5]),
            ];
        }
    };

    // Декартово произведение массивов (для всех комбинаций гамет).
    $cartesian = function(array $arrays): array {
        $result = [[]];
        foreach ($arrays as $key => $values) {
            $tmp = [];
            foreach ($result as $product) {
                foreach ($values as $val) {
                    $tmp[] = $product + [$key => $val];
                }
            }
            $result = $tmp;
        }
        return $result;
    };

    // Генерация гамет из генотипа для N локусов.
    $gametesN = function(array $genoN) use ($cartesian): array {
        $perLocus = [];
        foreach ($genoN as $locus => $pair) {
            $perLocus[$locus] = [$pair[0], $pair[1]]; // напр. ['S','s']
        }
        $combos = $cartesian($perLocus); // [['S'=>'S','D'=>'d',...], ...]
        return array_map(fn($g) => implode('', array_values($g)), $combos); // "SdP", "sDp", ...
    };

    // Комбинируем две гаметы -> генотип ребёнка по каждому локусу.
    $combineGametesN = function(string $ga, string $gb, array $locusOrder) use ($normalizePair): array {
        $child = [];
        for ($i=0; $i<count($locusOrder); $i++) {
            $L = $locusOrder[$i];
            $child[$L] = $normalizePair($ga[$i].$gb[$i]); // гарантируем Ss/Dd/Pp
        }
        return $child; // ['S'=>'Ss','D'=>'Dd', ('P'=>'Pp')]
    };

    // Фенотип: базовый цвет + опционально пегость.
    // Возвращает [читаемый_лейбл, cssКлассыЧерезПробел]
    $phenotypeN = function(array $genoN): array {
        $hasSpread  = isset($genoN['S']) && ($genoN['S'] !== 'ss'); // S_
        $isDilute   = isset($genoN['D']) && ($genoN['D'] === 'dd'); // dd
        $hasPiebald = isset($genoN['P']) && ($genoN['P'] !== 'pp'); // P_

        if     ($hasSpread && $isDilute) { $baseLabel='Dark (Spread) + Dilute';   $baseClass='pheno-dark-dilute'; }
        elseif ($hasSpread && !$isDilute){ $baseLabel='Dark (Spread)';            $baseClass='pheno-dark'; }
        elseif (!$hasSpread && $isDilute){ $baseLabel='Non-spread + Dilute';      $baseClass='pheno-blue-dilute'; }
        else                             { $baseLabel='Non-spread (wild-type)';  $baseClass='pheno-blue'; }

        if ($hasPiebald) return [$baseLabel.' + Piebald', $baseClass.' piebald-on'];
        return [$baseLabel, $baseClass];
    };

    // ===== Ввод =====
    $mode = (int) $r->input('mode', (int)$r->query('mode', 2)); // 2 или 3
    $mode = in_array($mode,[2,3]) ? $mode : 2;

    $def1 = $mode===2 ? 'SsDd'   : 'SsDdPp';
    $def2 = $mode===2 ? 'SsDd'   : 'SsDdPp';
    $parent1 = $r->input('parent1', $r->query('parent1', $def1));
    $parent2 = $r->input('parent2', $r->query('parent2', $def2));

    $locusOrder = $mode===2 ? ['S','D'] : ['S','D','P'];

    $data = [
        'mode'    => $mode,
        'parent1' => $parent1,
        'parent2' => $parent2,
        'errs'    => [],
        'computed'=> null,
    ];

    // ===== Расчёт =====
    if ($r->isMethod('post')) {
        $p1 = $parseGenoN($parent1, $mode);
        $p2 = $parseGenoN($parent2, $mode);

        if (!$p1) $data['errs'][] = $mode===2
            ? 'Родитель 1: формат SsDd, SSdd, ssDD.'
            : 'Родитель 1: формат SsDdPp, SSddPp, ssDDpp и т.п.';
        if (!$p2) $data['errs'][] = $mode===2
            ? 'Родитель 2: формат SsDd, SSdd, ssDD.'
            : 'Родитель 2: формат SsDdPp, SSddPp, ssDDpp и т.п.';

        // Нормализуем отображение родителей (чтобы было SsDd, а не sSDd)
        if ($p1) {
            $parent1 = implode('', array_map(fn($L) => $p1[$L], $locusOrder));
            $data['parent1'] = $parent1;
        }
        if ($p2) {
            $parent2 = implode('', array_map(fn($L) => $p2[$L], $locusOrder));
            $data['parent2'] = $parent2;
        }

        if ($p1 && $p2) {
            $g1 = $gametesN($p1);
            $g2 = $gametesN($p2);

            $rows = count($g1);
            $cols = count($g2);

            $grid = [];
            $genoCounts = [];
            $phenoCounts = [];

            for ($i=0; $i<$rows; $i++) {
                $row = [];
                for ($j=0; $j<$cols; $j++) {
                    $child = $combineGametesN($g1[$i], $g2[$j], $locusOrder);
                    [$label,$css] = $phenotypeN($child);

                    $genoKey = implode('|', array_map(fn($L)=>$child[$L], $locusOrder));
                    $row[] = [
                        'child'   => $child,
                        'label'   => $label,
                        'class'   => $css,
                        'gametes' => $g1[$i].' × '.$g2[$j],
                    ];

                    $genoCounts[$genoKey] = ($genoCounts[$genoKey] ?? 0) + 1;
                    $phenoCounts[$label]  = ($phenoCounts[$label]  ?? 0) + 1;
                }
                $grid[] = $row;
            }

            $total = $rows * $cols;
            ksort($genoCounts);
            ksort($phenoCounts);

            // ===== Объяснение результата =====
            $locnames = [
                'S' => 'Spread (S/s): доминантный S_ даёт тёмный окрас (заливка рисунка)',
                'D' => 'Dilute (D/d): разбавление проявляется только при dd (гоморецессивное)',
                'P' => 'Piebald (P/p): доминантный P_ даёт белые пятна (пегость)',
            ];

            $explain = [];
            $explain[] = "Скрещивание: {$parent1} × {$parent2}.";
            $explain[] = "Локусы: " . implode(', ', array_map(fn($L)=>$locnames[$L] ?? $L, $locusOrder)) . ".";
            $explain[] = "Гаметы родителя 1: " . implode(', ', $g1) . ". Гаметы родителя 2: " . implode(', ', $g2) . ".";
            $explain[] = "Предположения модели: независимое наследование локусов (без сцепления), полное доминирование для S и P; D/d осветляет только в dd.";

            $explain[] = "Распределение фенотипов (в %): " . implode('; ', array_map(
                function($k,$v) use ($total){ return "$k — ".round(100*$v/$total,2)."%"; },
                array_keys($phenoCounts),
                array_values($phenoCounts)
            )) . ".";

            if ($mode===2 && strcasecmp($parent1,'SsDd')===0 && strcasecmp($parent2,'SsDd')===0) {
                $explain[] = "Классический дигибридный анализ: SsDd × SsDd → ожидаемое фенотипическое соотношение 9:3:3:1 (S_ D_ : S_ dd : ss D_ : ss dd) ≈ 56.25% : 18.75% : 18.75% : 6.25%.";
            }

            if (count($phenoCounts)===1) {
                $only = array_key_first($phenoCounts);
                $explain[] = "Все потомки имеют один фенотип («{$only}»). Причина: каждый родитель даёт по одному типу гаметы по критичным локусам, либо рецессивный признак не может проявиться (например, доминант присутствует у всех потомков).";
            }

            if (in_array('S',$locusOrder)) $explain[] = "По S: наличие S (S_) → тёмный окрас; ss → «дикий» рисунок.";
            if (in_array('D',$locusOrder)) $explain[] = "По D: осветление только при dd; при D_ разбавления нет.";
            if (in_array('P',$locusOrder)) $explain[] = "По P: пегость при P_; при pp белых пятен нет.";

            $explainText = implode(' ', $explain);

            // ===== Сборка данных для шаблона =====
            $data['computed'] = [
                'locusOrder'  => $locusOrder,
                'gametes1'    => $g1,
                'gametes2'    => $g2,
                'grid'        => $grid,
                'genoFreq'    => array_map(fn($c)=>round(100*$c/$total,2), $genoCounts),
                'phenoFreq'   => array_map(fn($c)=>round(100*$c/$total,2), $phenoCounts),
                'explainText' => $explainText,
            ];
        }
    }

    return view('pigeons', $data);
});
