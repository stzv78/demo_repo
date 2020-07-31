<?php
namespace App\Models\Traits;

use Hamcrest\NullDescription;
use Illuminate\Support\Facades\Storage;

trait S3GetUrlTrait
{
    private function getFileUrl($key) {

        if (!$key) {
            return NULL;
        }

        $s3 = Storage::disk('s3_get');

        $client = $s3->getDriver()->getAdapter()->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key
        ]);

        $request = $client->createPresignedRequest($command, '+20 minutes');

        return (string) $request->getUri();
    }


}
