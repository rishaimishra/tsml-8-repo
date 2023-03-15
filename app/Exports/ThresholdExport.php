<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use App\Models\SoTempExcel;

class ThresholdExport implements FromCollection
{
	protected $getThresholdData;
	function __construct($getThresholdData) 
	{
	    $this->getThresholdData = $getThresholdData;
	} 
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $getThresholdData;
    }

    public function headings(): array
    {
        return [
            'Product',
            'Category', 
            'Sub Category',
            'Sizes Offered', 
            'Basic Price', 
            'Price Premium',
            'Prod Premium/Disc',
            'Interest Credit',
            'Special Discount', 
        ];
    }
}
