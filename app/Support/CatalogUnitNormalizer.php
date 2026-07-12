<?php

namespace App\Support;

class CatalogUnitNormalizer
{
    public function parse(?string $value, string $fallbackName = ''): array
    {
        $text = mb_strtolower(trim(($value ?: '').' '.$fallbackName));
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(kg|kilogram|g|gr|gram|ltr|liter|litre|l|ml|pcs|pc|piece|set|pack|box|roll)\b/u', $text, $match)) {
            $quantity = (float) str_replace(',', '.', $match[1]); $unit = $match[2];
        } else { $quantity = 1; $unit = strtoupper(trim($value ?: 'PCS')); }
        return match (strtolower($unit)) {
            'g','gr','gram' => ['pack_quantity'=>$quantity,'pack_unit'=>'G','normalized_quantity'=>$quantity/1000,'normalized_unit'=>'KG'],
            'kg','kilogram' => ['pack_quantity'=>$quantity,'pack_unit'=>'KG','normalized_quantity'=>$quantity,'normalized_unit'=>'KG'],
            'ml' => ['pack_quantity'=>$quantity,'pack_unit'=>'ML','normalized_quantity'=>$quantity/1000,'normalized_unit'=>'L'],
            'l','ltr','liter','litre' => ['pack_quantity'=>$quantity,'pack_unit'=>'L','normalized_quantity'=>$quantity,'normalized_unit'=>'L'],
            'pc','pcs','piece' => ['pack_quantity'=>$quantity,'pack_unit'=>'PCS','normalized_quantity'=>$quantity,'normalized_unit'=>'PCS'],
            default => ['pack_quantity'=>$quantity,'pack_unit'=>strtoupper($unit ?: 'PCS'),'normalized_quantity'=>$quantity,'normalized_unit'=>strtoupper($unit ?: 'PCS')],
        };
    }
}
