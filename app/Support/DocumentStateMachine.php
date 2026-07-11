<?php

namespace App\Support;

class DocumentStateMachine
{
    private const TRANSITIONS = [
        'purchase_request' => [
            'draft' => ['submitted'],
            'submitted' => ['approved', 'rejected'],
        ],
        'purchase_order' => [
            'draft' => ['submitted'],
            'submitted' => ['approved'],
            'approved' => ['partially_received', 'received'],
            'partially_received' => ['received'],
        ],
        'goods_receipt' => [
            'draft' => ['posted'],
        ],
        'stock_opname' => [
            'draft' => ['posted'],
        ],
    ];

    public static function allows(string $document, string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$document][$from] ?? [], true);
    }
}
