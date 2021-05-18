<?php

namespace App\Http\Controllers\Api\Kireimo;

use App\Http\Controllers\Controller;
use App\Model\Kireimo\News;

class NewsController extends Controller
{
    public function get(News $newsModel) {
        $newsResult = $newsModel->getNews();

        if ($newsResult->count() < 1) {
            // レスポンス返却(0件)
            return response()->json([
                'apiStatus' => 204,
                'count' => 0
            ]);
        }

        // レスポンスBody生成
        $responseBody = [];
        foreach($newsResult as $key => $value) {
            $responseBody[$key]['subject'] = $value->subject;
            $responseBody[$key]['url'] = $value->url;
            $responseBody[$key]['startDate'] = $value->start_date;
            $responseBody[$key]['endDate'] = $value->end_date;
            $responseBody[$key]['weight'] = $value->weight;
        }

        // 正常レスポンス返却
        return response()->json([
            'apiStatus' => 200,
            'count' => $newsResult->count(),
            'body' => $responseBody
        ]);
    }

}