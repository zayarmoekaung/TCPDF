<?php


// Include the main TCPDF library (search for installation path).
//require_once('/home/ec2-user/vendor/tecnickcom/tcpdf/tcpdf.php');
require_once('tcpdf_include.php');
//
// 利用方法
// $obj->setImageBase64Jpg($base64jpg);
//  または
// $obj->setImageFilename($fullpath);
// $obj->setInferText($json);
// $obj->setOutputFilename($fullpath); ...ふぁりるを保存する場合フルパス
// $obj->publish();
//
class new_pdf extends TCPDF 
{
  public function d_brand(){
    $this->tcpdflink = false;
  }
}

class SearchablePdf {

    private $pdf;

    private $data_author;
    private $data_title;
    private $data_subject;
    private $data_keywords;

    private $surface_scan_data;
    private $img_fn;
    private $img_fn_porl;
    private $img_fn_to_remove;
    private $output_fn;


    /////////////////////////////////////////////////////////////
    //
    // コンストラクタ
    //
    /////////////////////////////////////////////////////////////
    
    function __construct($porl="P", $pagesize="A4", $unit="mm", $lang="UTF-8"){

        // create new PDF document
        $this->pdf = new new_pdf($porl, $unit ,$pagesize, true, $lang, false);
        $this->pdf->d_brand();
       
    }
    /////////////////////////////////////////////////////////////
    //
    // PDF作成
    //
    /////////////////////////////////////////////////////////////
    public function drawPage(){

        /////////////////////////////////////////////////////////////
        // エラーチェック
        if($this->img_fn=="" || !file_exists($this->img_fn) || filesize($this->img_fn)==0){
            echo "error: nofile exists";
            //exit();
            return false;
        }


        /////////////////////////////////////////////////////////////
        // PDF作成事前作業

        // ページ作成・基本設定
        $this->init_pdf($this->img_fn_porl);
        // 個別設定
        $this->pdf->SetMargins( 0, 0, 0 ) ;
        $this->pdf->SetFont('YuGothic', '', 12);
        $this->pdf->SetTextColor( 255,0,0 );
        $this->pdf->SetAlpha(0);
        $this->pdf->SetAutoPageBreak(false);

        /////////////////////////////////////////////////////////////
        // SurfaceScanデータの埋め込み
        foreach($this->surface_scan_data as $data){
        
            // 位置計算
            $loc = $this->getLocation($data["boundingPoly"]["vertices"]);

            // セルの設定と文字の埋め込み
            $this->pdf->rotate($loc["degree"],$loc["x"],$loc["y"]);
            $this->pdf->setXY($loc["x"],$loc["y"]);
            $this->pdf->Cell($loc["w"], $loc["h"], trim($data["inferText"]), 1, 1, 'C', 0, '', 2);
            $this->pdf->rotate($loc["degree360"],$loc["x"],$loc["y"]);
        
        }

        
    }
    // PDFの出力
    public function publish(){


        //ob_end_clean();
        if($this->output_fn!=""){
            $this->pdf->Output($this->output_fn, "F");
        }else{
            $this->pdf->Output($this->output_fn, "I");
        }

        if($this->img_fn_to_remove!="" && file_exists($this->img_fn_to_remove)){
            unlink($this->img_fn_to_remove);
        }
        
    }



    

    /////////////////////////////////////////////////////////////
    //
    // その他メソッド
    //
    /////////////////////////////////////////////////////////////

    // JPGデータのタテヨコチェック
    private function getPorL($fn){

        $res = getimagesize($fn);
        $w = $res[0];
        $h = $res[1];
        if($w<$h) return "P";
        else return "L";

    }

    // 透明レイヤーの文字位置の取得
    // ★縦書き、ナナメってるものの対応はまだしていない
    private function getLocation($pos, $porl = ""){

        /////////////////////////////////////////////////
        // デフォルト値
        if($porl==""){
            $porl = $this->img_fn_porl;
        }

        // ProLでパラメータ設定
        if($porl=="P"){
            $a4w = 210;
            $a4h = 297;    
        }else{
            $a4w = 297;
            $a4h = 210;    
        }

        /////////////////////////////////////////////////
        // 角度の計算
        $x0 = $pos[0]["x"]*100;
        $x1 = $pos[1]["x"]*100;
        $y0 = $pos[0]["y"]*100;
        $y1 = $pos[1]["y"]*100;
        $xd = $x1 - $x0;
        $yd = $y1 - $y0;
        $pos["xd"] = $xd;
        $pos["yd"] = $yd;

        // それぞれの場合。
        if($xd!=0 && $yd!=0){
            $rad = atan($yd/$xd);
        }elseif($xd==0){
            // 画像は下向きがY方向
            if($yd<0) $rad= deg2rad(90);
            if($yd>0) $rad= deg2rad(270);

        }elseif($yd==0){
            if($xd>0) $rad= deg2rad(0);
            if($xd<0) $rad= deg2rad(180);

        }else{
        
            $rad=0;
        }
        $pos["angle"] = $rad;
        $pos["degree"] = rad2deg($rad);
        if($pos["degree"]<0) $pos["degree"] = 360+ $pos["degree"];
        $pos["degree360"] = 360-$pos["degree"];



        /////////////////////////////////////////////////
        // 基準位置の算出
        $pos["x"] = $pos[0]["x"] * $a4w;
        $pos["y"] = $pos[0]["y"] * $a4h;
        

        /////////////////////////////////////////////////
        // 原点(x0,y0)とし、そこから回転軸での幅の計算
        // いろんなデータが混じり合っているので0～3のみ対象とする
		// 座標の回転 https://keisan.casio.jp/exec/system/1496883774
        foreach($pos as $key => $val){
            if($key>0){
                $x = ($val["x"]-$pos[0]["x"])*$a4w;
                $y = ($val["y"]-$pos[0]["y"])*$a4h;
                $pos[$key]["x"] = $x*cos($rad)-$y*sin($rad);
                $pos[$key]["y"] = $x*sin($rad)+$y*cos($rad);
            }
        }

    
        // タテヨコ軸での枠の幅・高さ計算
        $w = ($pos[1]["x"] -  $pos[0]["x"] );
        $h = ($pos[2]["y"] -  $pos[1]["y"] );


        $pos["w"] = $w;
        $pos["h"] = $h;

        


        //print_r($pos);
        return $pos;

    }

    ///////////////////////////////////////////////////////////////////////////////
    // 透明レイヤーの文字位置の取得
    private function init_pdf($porl="P", $pagesize="A4", $unit="mm", $lang="UTF-8"){


        // set document information
        $this->pdf->SetCreator(PDF_CREATOR);
        $this->pdf->SetAuthor($this->data_author);
        $this->pdf->SetTitle($this->data_title);
        $this->pdf->SetSubject($this->data_subject);
        $this->pdf->SetKeywords($this->data_keywords);

        // set header and footer fonts
        $this->pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));

        // set default monospaced font
        $this->pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $this->pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(0);
        $this->pdf->SetFooterMargin(0);

        // remove default footer
        $this->pdf->setPrintFooter(false);

        // set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
            require_once(dirname(__FILE__).'/lang/eng.php');
            $this->pdf->setLanguageArray($l);
        }

        // add a page
        $this->pdf->AddPage($porl);


        // get the current page break margin
        $bMargin = $this->pdf->getBreakMargin();
        // get current auto-page-break mode
        $auto_page_break = $this->pdf->getAutoPageBreak();
        // disable auto-page-break
        $this->pdf->SetAutoPageBreak(false, 0);
        // set bacground image
        if($porl=="P"){
            $this->pdf->Image($this->img_fn, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }else{
            $this->pdf->Image($this->img_fn, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
        }
        // restore auto-page-break status
        $this->pdf->SetAutoPageBreak($auto_page_break, $bMargin);
        // set the starting point for the page content
        $this->pdf->setPageMark();



    }





    /////////////////////////////////////////////////////////////
    //
    // カプセル化用
    //
    /////////////////////////////////////////////////////////////

    // 画像ファイル名のセット ($this->output_fnは消す)
    public function setImageFilename($fn){
        // 既にテンポラリ画像がある婆は削除
        $this->removeTempImage();
        // 原稿のタテヨコ確認
        $this->img_fn_porl = $this->getPorL($fn);
        // 変数セット
        $this->img_fn = $fn;
    }

    // 画像ファイル名の保存 ($this->output_fnにテンポラリ名を入れておく)
    public function setImageBase64Jpg($base64jpg){
        
        if($base64jpg!=""){

            // 既にテンポラリ画像がある場合は削除
            $this->removeTempImage();

            // ファイル保存
            $tmp_fn = "/tmp/transparentPDF_".date("YmdHis")."_".rand(10000000,99999999).".jpg";
            file_put_contents($tmp_fn, base64_decode($base64jpg));

            // 原稿のタテヨコ確認
            $this->img_fn_porl = $this->getPorL($tmp_fn);

            // 変数セット
            $this->img_fn = $tmp_fn;
            $this->img_fn_to_remove = $tmp_fn;
        }
    }

    // テンポラリ画像の削除
    private function removeTempImage(){

        if($this->img_fn_to_remove!="" && file_exists($this->img_fn_to_remove)){
            unlink($this->img_fn_to_remove);
        }
        $this->img_fn_to_remove="";

    }


    // CLOVA OCRデータのセット
    public function setInferText($in){
        $this->surface_scan_data = json_decode($in, true);
    }
    // 出力ファイル名のセット
    public function setOutputFilename($fn=""){
        $this->output_fn = $fn;
    }

    // PDF各種情報のセット
    public function setPdfAuthor($in=""){
        $this->data_author = $in;
    }
    public function setPdfTitle($in=""){
        $this->data_title = $in;
    }
    public function setPdfSubject($in=""){
        $this->data_subject = $in;
    }
    public function setPdfKeywords($in=""){
        $this->data_keywords = $in;
    }




}
