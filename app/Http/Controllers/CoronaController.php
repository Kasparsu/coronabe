<?php


namespace App\Http\Controllers;




use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

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

    private function getCountryCodes($codes){
        $client = new Client(['base_uri' => 'https://restcountries.eu', 'verify' => false]);
        if(!Cache::has($codes)) {
            $resp = $client->get('rest/v2/alpha', ['query' => ['codes' => $codes]])->getBody()->getContents();
            Cache::put($codes, $resp, 3600);
            return \GuzzleHttp\json_decode($resp);
        }
        return \GuzzleHttp\json_decode(Cache::get($codes));
    }

    public function geojson(){
        $summary = \GuzzleHttp\json_decode($this->proxy('/summary'));
        $codes = array_map(function ($country) {
            return $country->CountryCode;
        }, $summary->Countries);
        $codesData = $this->getCountryCodes(implode(';', $codes));
        $codesCollection = new Collection($codesData);
        $codesCollection = $codesCollection->keyBy('alpha2Code');
        $countries = \GuzzleHttp\json_decode(File::get(storage_path('app/countries.geojson')));
        foreach ($countries->features as $feature){
            $match = false;
            foreach ($summary->Countries as $country){
                if($codesCollection->get($country->CountryCode) != null &&
                    $feature->id === $codesCollection->get($country->CountryCode)->alpha3Code){
                    $feature->properties->confirmed = $country->TotalConfirmed;
                    $feature->properties->deaths = $country->TotalDeaths;
                    $match = true;
                }
            }
            if(!$match){
                $feature->properties->confirmed = 0;
                $feature->properties->deaths = 0;
            }
        }
        return response(\GuzzleHttp\json_encode($countries));
    }
}
