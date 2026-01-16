<?php

namespace App\Actions\Lunch;

use App\Models\Enseigne;

class UpdateEnseigne
{
    /**
     * @param  array{name?: string, url_menu?: string|null, notes?: string|null, active?: bool}  $data
     */
    public function handle(Enseigne $enseigne, array $data): Enseigne
    {
        $enseigne->fill($data);
        $enseigne->save();

        return $enseigne;
    }
}
