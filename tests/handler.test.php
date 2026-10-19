<?php
declare(strict_types=1);
define('SWITCH_REVIEW_LIBRARY',true);
require __DIR__.'/../handler.php';
function check(bool $ok,string $message): void { if(!$ok) throw new RuntimeException($message); }
$base=['name'=>'Alex','business_name'=>'Example','email'=>'alex@example.test','phone'=>'','staff_size'=>'5–15','renewal'=>'Not sure','consent'=>'yes','concerns'=>['Slow response','Something else'],'other_concern'=>'Details','utm_source'=>'facebook','utm_campaign'=>'switch-review'];
[$valid,$errors]=sr_validate($base);check(!$errors,'valid submission');
foreach(['name','business_name','email','staff_size','renewal','consent'] as $field) { $bad=$base;unset($bad[$field]);check(isset(sr_validate($bad)[1][$field]),'required '.$field); }
$bad=$base;$bad['email']='bad';check(isset(sr_validate($bad)[1]['email']),'bad email');
$bad=$base;$bad['other_concern']=str_repeat('é',301);check(isset(sr_validate($bad)[1]['other_concern']),'unicode character length');
$bad=$base;$bad['concerns']=['unknown'];check(isset(sr_validate($bad)[1]['concerns']),'bad concerns');
$bad=$base;$bad['concerns']='bad';check(isset(sr_validate($bad)[1]['concerns']),'malformed concerns');
check(sr_honeypot(['website'=>'bot']) && !sr_honeypot(['website'=>'']),'honeypot');
$parsed=sr_parse('concerns=Slow+response&concerns=Security+worries&utm_source=facebook&fbclid=click');
check(count($parsed['concerns'])===2 && $parsed['utm_source']==='facebook' && $parsed['fbclid']==='click','repeated choices and UTM');
try { sr_parse('name=a&name=b');throw new RuntimeException('duplicate accepted'); }catch(InvalidArgumentException $e){}
$rate=sys_get_temp_dir().'/switch-review-test-'.bin2hex(random_bytes(8));mkdir($rate,0700);
try {
    for($i=0;$i<5;$i++) check(sr_rate($rate,str_repeat('x',32),'127.0.0.1',10000),'rate allowed');
    check(!sr_rate($rate,str_repeat('x',32),'127.0.0.1',10000),'sixth denied');
    check(sr_rate($rate,str_repeat('x',32),'127.0.0.1',13601),'rate expires');
    check(sr_rate($rate,str_repeat('x',32),'127.0.0.2',10000),'independent IP');
}finally{foreach(glob($rate.'/*.json') as $file) unlink($file);rmdir($rate);}
$html=sr_render($valid,['name'=>'Fix name']);
check(str_contains($html,'value="Alex"') && str_contains($html,'value="facebook"'),'no-JS preserves values');
check(str_contains($html,'value="Slow response" checked') && str_contains($html,'>Details</textarea>'),'no-JS preserves choices');
check(!str_contains($html,'<script ') && str_contains($html,'action="/api/switch-review"'),'no-JS validation rerender');
check(str_contains($html,'href="/style.css"'),'POST response assets');
check(!str_contains(sr_render([],[],true),'<form') && str_contains(sr_render([],[],true),'data-step="6">'),'no-JS confirmation');
$attack=$valid;$attack['name']='"><img src=x onerror=alert(1)>';
check(!str_contains(sr_render($attack),'<img src=x'),'HTML escape');
class TestStatement extends PDOStatement {
    public array $args=[];
    public function execute(?array $params=null): bool { $this->args=$params??[];return true; }
}
class TestPDO extends PDO {
    public string $sql='';public TestStatement $statement;
    public function __construct(){ $this->statement=new TestStatement(); }
    public function prepare(string $query,array $options=[]): PDOStatement|false { $this->sql=$query;return $this->statement; }
}
$pdo=new TestPDO();sr_insert($pdo,$valid);
check(str_starts_with($pdo->sql,'INSERT INTO switch_review_leads') && !str_contains($pdo->sql,'SELECT'),'INSERT only');
check(in_array('facebook',$pdo->statement->args,true),'insert UTM');
check(!str_contains($pdo->sql,'Alex') && in_array('Alex',$pdo->statement->args,true),'prepared parameters');
$small=$valid;$small['staff_size']='1–4';sr_insert($pdo,$small);check($pdo->statement->args[9]===1,'outside target flag');
putenv('SWITCH_PUBLIC_ORIGIN=https://switchtest.theitguys.us');
$_SERVER=['HTTPS'=>'on','REMOTE_ADDR'=>'127.0.0.1','REQUEST_METHOD'=>'POST','REQUEST_URI'=>'/api/switch-review','HTTP_ACCEPT'=>'application/json','CONTENT_LENGTH'=>10241];
ob_start();sr_main();$body=ob_get_clean();check(http_response_code()===413 && json_decode($body,true)['ok']===false,'10KB request limit');
$_SERVER['CONTENT_LENGTH']=0;$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';$_GET=['utm_source'=>'facebook'];
ob_start();sr_main();$body=ob_get_clean();check(str_contains($body,'name="utm_source" value="facebook"'),'no-JS GET attribution');
echo "PHP handler checks passed: validation, honeypot, repeated choices, 5/hour limit, body size, attribution, prepared INSERT, fallback, escaping.\n";
