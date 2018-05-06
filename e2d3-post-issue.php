<?php
/*
Plugin Name: 不具合報告送る君
*/
class e2d3_post_issue
{
    
    // 設定
    public $global_github_conf = array(
        "gitUser" => "kensukegoto",
        "gitRepo" => "e2d3-post-issue",
        "token" => "commit時は削除",
        "repo" => ''
    );
    
    
    // 画像の保存場所を作成
    public $issue_image_dirname;
    public $issue_image_urlname;
    
    function __construct(){
        
        register_activation_hook(__FILE__,array($this,'activate'));

        $this->issue_image_dirname  = wp_upload_dir()['basedir'].'/issue_image';
        $this->issue_image_urlname  = wp_upload_dir()['baseurl'].'/issue_image';
        $this->global_github_conf['repo'] = "https://api.github.com/repos/{$this->global_github_conf["gitUser"]}/{$this->global_github_conf["gitRepo"]}/";
        
        add_action('wpcf7_before_send_mail','post_issue');
        add_filter('wpcf7_validate', 'wpcf7_validate_customize', 11, 2);
        
    }
    
    function activate(){
        
        $this->issue_image_dirname  = wp_upload_dir()['basedir'].'/issue_image';
        $this->issue_image_urlname  = wp_upload_dir()['baseurl'].'/issue_image';
        
        if ( ! file_exists( $this->issue_image_dirname ) ) {
            wp_mkdir_p( $this->issue_image_dirname );
        }   
        flush_rewrite_rules();
    }

}

$e2d3_postman = new e2d3_post_issue();


function post_issue($contact_form){
    
    global $e2d3_postman;



    $submission = WPCF7_Submission::get_instance();

    if ( $submission ) {
        $post = $submission->get_posted_data();
        $files = $submission->uploaded_files();
    }

    extract($e2d3_postman->global_github_conf);

    // postされたテキストをサニタイジング
    function prepare_for_markdown($ans){

        $gitMarkDown = preg_quote("`|#>:-*_+!.\?",'/');
        // メタ文字のエスケープ
        $ans = preg_replace("/([{$gitMarkDown}])/",'\\\$1',$ans);
        // $ans = trim(preg_replace('/\t/g', '', $ans));
        $ans = preg_replace('/(\t|\r\n|\r|\n)/s', '<br>', $ans);

        return $ans;
    }

    $answer = array_map("prepare_for_markdown", $post);

    extract($answer,EXTR_SKIP);


    $ans_1 = "|トラブルの内容について|\n";
    $ans_1 .= "|-|\n";
    $ans_1 .= "|**どの機能で、トラブルが起こりましたか？**|\n";
    $ans_1 .= "|{$ans_1_1}|\n";
    $ans_1 .= "|**トラブルは、どのくらいの確率で発生しますか？**|\n";
    $ans_1 .= "|{$ans_1_2}|\n";
    $ans_1 .= "**トラブルの内容を詳しく教えてください**\n";
    $ans_1 .= "|{$ans_1_3}|\n\n";


    $ans_2 = "|トラブルのあったPC＋Excel環境について|\n";
    $ans_2 .= "|-|\n";
    $ans_2 .= "|**どのOSを使われていますか？**|\n";
    $ans_2 .= "|{$ans_2_1}|\n";
    $ans_2 .= "|**OSのバージョンを詳しく教えてください**|\n";
    $ans_2 .= "|{$ans_2_2}|\n";
    $ans_2 .= "**Excelのバージョンを詳しく教えてください**\n";
    $ans_2 .= "|{$ans_2_3}|\n";
    $ans_2 .= "|**(ExcelOnlineをご利用の場合)どのブラウザを使われていますか？**|\n";
    $ans_2 .= "|{$ans_2_4}|\n";
    $ans_2 .= "** (ExcelOnlineをご利用の場合)ブラウザのバージョンを詳しく教えてください**\n";
    $ans_2 .= "|{$ans_2_5}|\n\n";

    $ans_3 = "|編集されていたExcelファイルについて|\n";
    $ans_3 .= "|-|\n";
    $ans_3 .= "|**Excelで操作されていたファイルは、どこに保存されていましたか？**|\n";
    $ans_3 .= "|{$ans_3_1}|\n\n";

    $ans_4 = "|その他申し送り事項|\n";
    $ans_4 .= "|-|\n";
    $ans_4 .= "|**その他申し送り事項がございましたら、こちらに記述をお願いします。**|\n";
    $ans_4 .= "|{$ans_4_1}|\n\n";

    $ans = $ans_1.$ans_2.$ans_3.$ans_4;              

    /* ここまで　テキスト処理　ここまで */
    /* ここから　画像処理　ここから */

    $usr_imgs = $files;

    function prepare_for_post_imgs($img){
        
        global $e2d3_postman;
    

        $issue_image_dirname = $e2d3_postman->issue_image_dirname;
        $issue_image_urlname = $e2d3_postman->issue_image_urlname;


        $img_name = make_img_name($img);

        copy($img, $issue_image_dirname.'/'.$img_name);


        // 設定項目
        extract($e2d3_postman->global_github_conf);

        $ret = [];

        $ret['img_path'] = $issue_image_urlname.'/'.$img_name;;

        return $ret;

    }

    //　ファイル名の作成
    function make_img_name($img_tmp){

        $mime = mime_content_type($img_tmp);
        $mime = explode('/',$mime);

        list($usec, $sec) = explode(' ', microtime());
        return ($sec + $usec * 1000000).".".$mime[1];
    }


    function set_img_markdown($img){
        $img_path = $img['img_path'];
        return '<img src="'.$img_path.'" alt="">';
    }

    $usr_imgs = array_map('prepare_for_post_imgs',$usr_imgs);
//    array_walk($usr_imgs,"post_usr_imgs");

    // 画像のマークダウンを作成
    $img_markdown = array_map('set_img_markdown',$usr_imgs);
    $img_markdown = array_values($img_markdown);


    // 画像のマークダウンを追加
    for($i=0,$l=count($img_markdown);$i<$l;$i++){

        $str = $img_markdown[$i];
        $str = ($i!==($l-1))?$str."\n\n":$str;
        $ans .= $str;

    }
    
    
    
    // タイトルの作成
    $title_1_3 = str_replace(PHP_EOL, '', $post["ans_1_3"]);
    $title = $ans_1_1." : ".mb_substr($title_1_3,0,60);
    
    

    // 以下、テキストの整形と送信
    $issue = json_encode(
        array(
            "title"=>$title,
            "body"=>$ans
        ),
        JSON_PRETTY_PRINT);

    $issueData = array(
        "repo" => $repo."issues",
        "issue" => $issue,
        "ua" => $gitUser,
        "token"=> $token
    );
    
    
    function post_git_issue(array $args) {


        extract($args);

        $header = [
            'Content-Type: application/json',
            'Authorization: token '.$token
        ];

        // RETURNTRANSFER exec時に結果出力を回避
        $options = array(
            CURLOPT_URL => $repo,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_POSTFIELDS => $issue,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_RETURNTRANSFER => true
        );

        $ch = curl_init();

        curl_setopt_array($ch, $options);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    
    $res = post_git_issue($issueData);


}

 
function wpcf7_validate_customize($result,$tags){
    
    $submission = WPCF7_Submission::get_instance();
 
    if ( $submission ) {
        $files = $submission->uploaded_files();
    }
    
    
    
    
    function get_mime($img){
        
        $finfo  = finfo_open(FILEINFO_MIME_TYPE);
          
        $mime = finfo_file($finfo, $img);
        
        finfo_close($finfo);

        
        return $mime;
    }

        

    foreach( $tags as $tag ){

        $type = $tag['type'];
        $name = $tag['name'];

        if($type=="file" && isset($files[$name])){
            
            $mimeList = ["image/gif","image/png","image/jpeg","image/jpg"];
            
            $mime1 = $_FILES[$name]["type"];
            
            if(in_array($mime1,$mimeList)){
                $mime2 = get_mime($files[$name]);
                if(!in_array($mime2,$mimeList)){
                    $result->invalidate( $tag,"ファイル名が不正です");
                }
            }
            
        }


    }
    
    if(empty($_POST)){
        die();
    }
    
    return $result;
}
