<?php

namespace App\Imports;

use App\Approvisionnement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FlottageImport implements ToCollection, WithHeadingRow
{
    public const NUM_ROW = '';

    function __construct($x){

        $this->NUM_ROW = $x;

    } 

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) 
        {
            Approvisionnement::new([
                'name' => $row['0'],
            ]);
        }
    }

    public function headingRow(): int
    {
        return $this->NUM_ROW;
    }
}


