<?php
/*
Plugin Name: POST ISSUE E2D3
*/
class e2d3_post_issue
{
    
    // 設定
    public $global_github_conf = array();
    
    
    // 画像の保存場所を作成
    public $issue_image_dirname;
    public $issue_image_urlname;
    
    public function __construct(){
        
        register_activation_hook(__FILE__,array($this,'activate'));
        
        // 投稿画面を改造
        add_action('admin_menu', array($this,'set_git_token'));
        add_action('admin_head', array($this,'add_css'));   
        
        $options = get_option('e2d3_post_issue_token');
        $defaults = array(
            "gitUser" => "",
            "gitRepo" => "",
            "token" => "",
            "repo" => ""
        );
        $this->global_github_conf = wp_parse_args($options,$defaults);
        
        

        // ChromePhp::log($this->global_github_conf);

        $this->issue_image_dirname  = wp_upload_dir()['basedir'].'/issue_image';
        $this->issue_image_urlname  = wp_upload_dir()['baseurl'].'/issue_image';
        
        add_action('wpcf7_before_send_mail','post_issue');
        add_filter('wpcf7_validate', 'wpcf7_validate_customize', 11, 2);
        
    }
    
    public function activate(){
        
        $this->issue_image_dirname  = wp_upload_dir()['basedir'].'/issue_image';
        $this->issue_image_urlname  = wp_upload_dir()['baseurl'].'/issue_image';
        
        if ( ! file_exists( $this->issue_image_dirname ) ) {
            wp_mkdir_p( $this->issue_image_dirname );
        }   
        flush_rewrite_rules();
        
        update_option('e2d3_post_issue_token',$this->global_github_conf);
    }
    
    
    public function add_css(){
        // global $po;
        
        if(isset($_GET['page'])&&$_GET['page']==='e2d3-post-issue.php'){
    
    ?>
        <link type="text/css" href="<?php echo plugins_url( 'style.css', __FILE__ ); ?>" rel="stylesheet" />
    <?php
        }
    }
    
    
    public function set_git_token(){
        
        add_options_page('GitHubの情報を設定','不具合報告（GitHub）','manage_options','e2d3-post-issue.php',array($this,'option_page'));
             
        
    }
    
    public function option_page(){
    ?>
      <div class="wrap">
          <h2>GitHubの情報を設定</h2>
        </div>
    <?php
        
        $conf = $this->global_github_conf;
        
        if(isset($_POST['special_nonce'])){
            
            check_admin_referer('special_action','special_nonce');
            
            $gitUser = '';
            $gitRepo = '';
            $token = '';
            
            if(isset($_POST['gitUser']) && $_POST['gitUser']!==''){
                $gitUser = $_POST['gitUser'];
            }
            if(isset($_POST['gitRepo']) && $_POST['gitRepo']!==''){
                $gitRepo = $_POST['gitRepo'];
            }
            if(isset($_POST['token']) && $_POST['token']!==''){
                $token = $_POST['token'];
            }
            
            $repo = "https://api.github.com/repos/{$gitUser}/{$gitRepo}/";
            
            $conf = array(
                "gitUser" => $gitUser,
                "gitRepo" => $gitRepo,
                "token" => $token,
                "repo" => $repo
            );
            
            update_option('e2d3_post_issue_token',$conf);
            
            echo '<p class="txt-save">保存しました。</p>';
        }
    ?>
        <div class="explain">
            <p>①「所有者名」にissueの送信先のリポジトリの所有ユーザー名</p>
            <p>②「リポジトリ名」にissueの送信先のリポジトリ名</p>
            <p>③「トークン」に発行したpersonal access token</p>
            <p>ex.<br>
            e2d3/e2d3のissueに送信するには「所有者名」にe2d3、「リポジトリ名」にe2d3と入力。<br>
            「トークン」の入力は？<br>
            基本は所有者が発行したトークンを使用するが、不具合送信報告用ユーザーを作成して、適切に権限を与えている場合は、
            不具合報告送信用ユーザーが発行したトークンを使用できる。
            </p>
        </div>
       <form class="post-issue-form" action="" method="post">
           <?php wp_nonce_field('special_action','special_nonce'); ?>
            <p>所有者名</p>
            <input type="text" name="gitUser" value="<?php echo $conf['gitUser']; ?>">
            <p>リポジトリ名</p>
            <input type="text" name="gitRepo" value="<?php echo $conf['gitRepo']; ?>">
            <p>トークン</p>
            <input type="text" name="token" value="<?php echo $conf['token']; ?>">
            <?php submit_button(); ?>
       </form>
    <?php
        
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
