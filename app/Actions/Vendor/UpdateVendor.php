<?php

namespace App\Actions\Vendor;

use App\Models\Vendor;

class UpdateVendor
{
    /**
     * @param  array{name?: string, url_menu?: string|null, notes?: string|null, active?: bool}  $data
     */
    public function handle(Vendor $vendor, array $data): Vendor
    {
        $vendor->fill($data);
        $vendor->save();

        return $vendor;
    }
}
