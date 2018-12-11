<?php

namespace App\Http\Controllers;

use App\Item;
use Illuminate\Http\Request;

use Validator;

require_once __DIR__."/../../ImageDataInfo/ImageData.php";
use App\ImageDataInfo;

class ItemController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Item::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // バリデーションをし、エラーならjsonで返す
        if($response = $this->validation($request)){
            return \Response::json($response);
        }

        $item = new Item;

        $item->name         = $request->name;
        $item->description  = $request->description;
        $item->price        = $request->price;

        // ファイルのデータがNULLだったら現在受け取っている情報だけでデータを構成
        if($request->img == NULL){
            $item->save();
            return \Response::json(array(
                'status' => 'create',
                'data' => $item->name
            ),201);
        }
        
        // データのMIMEタイプをバリデーションをし、エラーならjsonで返す
        if($response = $this->validationMimeType($request)){
            return \Response::json($response);
        };

        // 画像ファイルの情報を取得
        $img_info = new ImageDataInfo\ImageDataBase64($request->img);

        // 画像情報を追加
        $item->mime     = $img_info->getMime();
        $item->raw_data = base64_decode($request->img);

        // データベースに書き込み
        $item->save();

        // ステータスと商品名をjson形式でレスポンス
        return \Response::json(array(
            'status' => 'create',
            'data' => $item->name
        ),201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function show(Item $item)
    {  
        // 対象の商品情報の中に画像データがある場合はBase64形式に変換する
        if ($item->raw_data != NULL && $item->mime != NULL)
        {
            $item->raw_data = base64_encode($item->raw_data);
        }
        // ステータスと商品情報をjson形式でレスポンス
        return \Response::json(array(
            'status' => 'FOUND',
            'data' => $item
        ),200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Item $item)
    {
        
        // バリデーションをし、エラーならjsonで返す
        if($response = $this->validation($request)){
            return \Response::json($response);
        }

        $item->name         = $request->name;
        $item->description  = $request->description;
        $item->price        = $request->price;

        // ファイルのデータがNULLだったら現在受け取っている情報だけでデータを構成
        if($request->img == NULL){

            // 画像に関する情報はなしに設定
            $item->mime     = NULL;
            $item->raw_data = NULL;

            // データベースに書き込み
            $item->save();
            return \Response::json(array(
                'status' => 'update',
                'data' => $item->name
            ),200);
        }

        // データのMIMEタイプをバリデーションをし、エラーならjsonで返す
        if($response = $this->validationMimeType($request)){
            return \Response::json($response);
        };

        // 画像ファイルの情報を取得
        $img_info = new ImageDataInfo\ImageDataBase64($request->img);

        // 画像情報を追加
        $item->mime     = $img_info->getMime();
        $item->raw_data = base64_decode($request->img);

        // データベースに書き込み
        $item->save();

        // ステータスと商品名をjson形式でレスポンス
        return \Response::json(array(
            'status' => 'update',
            'data' => $item->name
        ),200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Item  $item
     * @return \Illuminate\Http\Response
     */
    public function destroy(Item $item)
    {
        // データベースから削除
        $item->delete();

        // ステータスと商品名をjson形式でレスポンス
        return \Response::json(array(
            'status' => 'delete',
            'data' => $item->name
        ),200);
    }

    /**
     * 検索
     *
     * @param  $keyword
     * @return \Illuminate\Http\Response
     */
    public function search($keyword)
    {
        // データベース内検索
        $items = Item::where('name', 'LIKE', '%'.$keyword.'%')->get();

        if (count($items) == 0) {
            // ヒットしない時、ステータスのみjson形式でレスポンス
            return \Response::json(array(
                'status' => 'NOT-FOUND'
            ),200);
          } else {
            // ヒットしない時、ステータスと{id,商品名,価格}をjson形式でレスポンス
            $data = [];
            foreach ($items as $item) {
                $data[] = [
                    'id'    => $item->id,
                    'name'  => $item->name,
                    'price' => $item->price,
                ];
            }
            return \Response::json(array(
                'status' => 'FOUND',
                'data' => $data
            ),200);
          }
    }




    /**
     * バリデーション
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validation($request)
    {
        //バリデーションルール
        $rules = [
            'name'       =>'required|string|max:100',
            'description'=>'required|string|max:500',
            'price'      =>'required|integer|min:0',
        ];

        //バリデーション
        $validation = Validator::make($request->all(),$rules);

        //バリデーションエラーの時ステータスをjson形式でレスポンス
        if($validation->fails())
        {
            $response["status"] = "Varidation Error";
            return $response;
        }
    }



    /**
     * MIMEタイプのバリデーション
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validationMimeType($request)
    {
        // 与えられたファイルのMIME-typeを取得
        $mime = ImageDataInfo\getMimeType(base64_decode($request->img));
        // mimetypeのエラーチェック
        if(ImageDataInfo\checkMimeType($mime) == "Other"){
            $response["status"] = "MIMEtype Error";
            return $response;
        }
    }
}
