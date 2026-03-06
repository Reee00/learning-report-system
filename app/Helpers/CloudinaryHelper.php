<?php

namespace App\Helpers;

class CloudinaryHelper
{
    public static function upload($filePath, $folder = 'lrs')
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        $timestamp = time();
        $params    = "folder={$folder}&timestamp={$timestamp}{$apiSecret}";
        $signature = sha1($params);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.cloudinary.com/v1_1/{$cloudName}/auto/upload",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'      => new \CURLFile($filePath),
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder'    => $folder,
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }

    public static function delete($publicId)
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey    = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        $timestamp = time();
        $signature = sha1("public_id={$publicId}&timestamp={$timestamp}{$apiSecret}");

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.cloudinary.com/v1_1/{$cloudName}/image/destroy",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'public_id' => $publicId,
                'api_key'   => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
}