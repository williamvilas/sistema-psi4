<?php

declare(strict_types=1);

namespace App\Http\Controllers;

class DocumentIssuerController extends Controller
{
    public function paginate()
    {
        return $this->response(function () {

            return [
                'html' => view('document-issuer.paginate')->render(),
            ];
        });
    }
}
