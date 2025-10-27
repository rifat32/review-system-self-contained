<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;


use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class CommonImport implements ToCollection
{
    protected $fileContent;

    public function __construct(UploadedFile $file)
    {
        $this->fileContent = $file->get();
    }

    public function collection(Collection $rows)
    {
        return $rows->toArray();
    }
}
