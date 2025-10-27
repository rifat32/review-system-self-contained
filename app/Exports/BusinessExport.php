<?php

namespace App\Exports;

use App\Models\Business;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;

class BusinessExport implements FromCollection
{
    protected $businesses;

    public function __construct($businesses)
    {
        $this->businesses = $businesses;
    }

    public function view(): View
    {
        return view('export.businesses', ["businesses" => $this->businesses]);
    }

    public function collection()
    {
        // If needed, you can return a collection here,
        // but for this case, the data is passed directly via the constructor and used in the view.
    }

    public function map($business): array
    {
        // Assuming that we want to map the columns for exporting.
        return [
            $business->Name,
            $business->created_at->format('d-m-Y'), // Registration Date
            $business->owner_name ?? 'N/A', // Assuming owner name exists in the database, or replace with a default
            $business->Status,
        ];
    }

}
