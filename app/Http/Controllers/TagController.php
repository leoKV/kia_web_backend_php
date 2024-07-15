<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    // public function getTagsByTipoTag()
    // {
    //     $tags = DB::select('SELECT * FROM sps_tag_info()');
    //     foreach ($tags as &$tag) {
    //         $tag->tags = $tag->tags ? array_map(function($value) {
    //             return trim($value, ' "');
    //         }, explode(',', trim($tag->tags, '{}'))) : [];
    //     }
    //     return response()->json($tags);
    // }

    public function getTagsByTipoTag()
    {
        $tags = Cache::remember('tags_by_tipo_tag', 60, function() {
            return DB::select('SELECT * FROM sps_tag_info()');
        });
        foreach ($tags as &$tag) {
            $tag->tags = $tag->tags ? array_map(function($value) {
                return trim($value, ' "');
            }, explode(',', trim($tag->tags, '{}'))) : [];
        }
        return response()->json($tags);
    }
}
