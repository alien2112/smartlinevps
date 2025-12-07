<?php
namespace App\Traits;

use Illuminate\Http\JsonResponse;
// FROM BABIATO
trait ActivationClass
{
public function dmvf($request)
{
// Sempre define as sessões e retorna que a licença é válida
session()->put(base64_decode('cHVyY2hhc2Vfa2V5'), $request[base64_decode('cHVyY2hhc2Vfa2V5')]); // pk
session()->put(base64_decode('dXNlcm5hbWU='), $request[base64_decode('dXNlcm5hbWU=')]); // un
return base64_decode('c3RlcDM='); // s3
}

public function actch(): JsonResponse
{
// Sempre retorna que a licença está ativa
return response()->json(['active' => true]);
}

public function is_local(): bool
{
// Sempre retorna true para evitar verificações de IP
return true;
}
}