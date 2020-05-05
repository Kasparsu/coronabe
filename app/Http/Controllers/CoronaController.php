<?php


namespace App\Http\Controllers;




use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;

class CoronaController extends Controller
{
    public function proxy($url){
        $key = $url.\GuzzleHttp\json_encode(request()->query->all());
        if(!Cache::has($key)) {
            $client = new Client(['verify' => false]);
            $resp = $client->get('https://api.covid19api.com/' . $url)->getBody()->getContents();
            Cache::put($key, $resp, 3600);
            return $resp;
        }
        return Cache::get($key);
    }
}
