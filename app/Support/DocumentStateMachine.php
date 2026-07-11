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
            'approved' => ['partial_received', 'received'],
            'partial_received' => ['received', 'approved'],
            'received' => ['partial_received', 'approved'],
        ],
        'goods_receipt' => [
            'draft' => ['posted'],
            'posted' => ['reversed'],
        ],
        'stock_opname' => [
            'draft' => ['posted'],
            'posted' => ['reversed'],
        ],
    ];

    public static function allows(string $document, string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$document][$from] ?? [], true);
    }
}
