<?php




namespace App\Utiles;
use Illuminate\Support\Facades\Storage;

abstract class ImageFromBase64
{

    /** 
     * Sauvegarde de l'image base 64
     * @param $base_64_image
     * @param $storage_path
     * @return string
     */
    public static function imageFromBase64AndSave($base_64_image, $storage_path)
    {
        
        // Get image name, image extension and convert to normal image for base 64 image
        $image_parts = explode(";base64,", $base_64_image);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];        
        $normal_image_file = base64_decode($image_parts[1]);        
        $server_image_name = str_random(40) . '.' . $image_type;

        // Store file in server path
        $server_image_name_path = $storage_path . $server_image_name;        
        Storage::put($server_image_name_path, $normal_image_file);

        // Return serve path
        return $server_image_name_path;
        
    } 
    

    
    

    

}



