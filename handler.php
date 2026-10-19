<?php
declare(strict_types=1);

// The only public PHP entry point. No sales code, sessions, or mail credentials.
function sr_headers(): void {
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
    header('Strict-Transport-Security: max-age=31536000');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
}
function sr_config(): array {
    $config = [];
    $file = getenv('SWITCH_CONFIG_FILE');
    // Site-local sibling directory outside public_html; no hosting-wide access needed.
    if (!$file && is_file(dirname(__DIR__).'/switch-private/config.php')) $file=dirname(__DIR__).'/switch-private/config.php';
    if ($file) {
        $real = realpath($file);
        if (!$real || str_starts_with($real, realpath(__DIR__) . DIRECTORY_SEPARATOR)) throw new RuntimeException('config_location');
        $config = require $real;
        if (!is_array($config)) throw new RuntimeException('config_format');
    }
    foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','DB_SSL_CA','RATE_DIR','RATE_SECRET','PUBLIC_ORIGIN','TRUSTED_PROXIES'] as $key) {
        $value = getenv('SWITCH_' . $key);
        if ($value !== false) $config[$key] = $value;
    }
    return $config;
}
function sr_parse(string $body): array {
    $out = [];
    foreach (explode('&', $body) as $pair) {
        if ($pair === '') continue;
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $key = urldecode($key); $value = urldecode($value);
        if ($key === 'concerns' || $key === 'concerns[]') $out['concerns'][] = $value;
        elseif (array_key_exists($key, $out)) throw new InvalidArgumentException('Duplicate field.');
        else $out[$key] = $value;
    }
    return $out;
}
function sr_validate(array $data): array {
    $errors = [];
    $limits = ['name'=>120,'business_name'=>160,'email'=>200,'phone'=>40,'other_concern'=>300,'utm_source'=>2000,'utm_medium'=>2000,'utm_campaign'=>2000,'utm_content'=>2000,'fbclid'=>2000];
    foreach ($limits as $key=>$max) {
        $value = $data[$key] ?? '';
        if (!is_string($value) || !preg_match('//u', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) { $errors[$key] = 'Enter valid text.'; continue; }
        $data[$key] = trim($value);
        if (preg_match_all('/./us', $data[$key]) > $max) $errors[$key] = "Use at most $max characters.";
    }
    foreach (['name'=>'Name','business_name'=>'Business name','email'=>'Work email'] as $key=>$label) if (($data[$key] ?? '') === '') $errors[$key] = "$label is required.";
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid work email (200 characters maximum).';
    if (!in_array($data['staff_size'] ?? '', ['1–4','5–15','16–30','31–50','50+'], true)) $errors['staff_size'] = 'Choose your team size.';
    if (!in_array($data['renewal'] ?? '', ['Within 3 months','3–6 months','6–12 months','Not sure','No contract'], true)) $errors['renewal'] = 'Choose your renewal window.';
    $allowed = ['Slow response','Problems keep coming back','Too expensive','Security worries',"Can't get our own passwords/accounts",'Something else'];
    $concerns = $data['concerns'] ?? [];
    if (!is_array($concerns) || count($concerns)>6 || array_filter($concerns, fn($v)=>!is_string($v) || !in_array($v,$allowed,true))) $errors['concerns'] = 'Choose valid concerns.';
    else $data['concerns'] = array_values(array_unique($concerns));
    if (($data['consent'] ?? '') !== 'yes') $errors['consent'] = 'Please agree to be contacted about your review.';
    if (!is_array($data['concerns'] ?? null) || !in_array('Something else', $data['concerns'], true)) $data['other_concern'] = '';
    return [$data, $errors];
}
function sr_rate(string $directory, string $secret, string $ip, int $now): bool {
    $root = realpath($directory);
    if (!$root || strlen($secret)<32 || str_starts_with($root, realpath(__DIR__) . DIRECTORY_SEPARATOR) || $root===realpath(__DIR__)) throw new RuntimeException('rate_config');
    $file = $root . DIRECTORY_SEPARATOR . hash_hmac('sha256', $ip, $secret) . '.json';
    $handle = fopen($file, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('rate_lock');
    try {
        $raw = stream_get_contents($handle);
        $times = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($times)) throw new RuntimeException('rate_data');
        $times = array_values(array_filter($times, fn($t)=>is_int($t) && $t>$now-3600));
        if (count($times)>=5) return false;
        $times[]=$now;
        rewind($handle);
        if (!ftruncate($handle,0) || fwrite($handle,json_encode($times,JSON_THROW_ON_ERROR))===false || !fflush($handle)) throw new RuntimeException('rate_write');
        return true;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}
function sr_escape(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sr_honeypot(array $data): bool { return isset($data['website']) && (!is_string($data['website']) || trim($data['website'])!==''); }
function sr_render(array $values=[], array $errors=[], bool $success=false): string {
    $html = file_get_contents(__DIR__ . '/index.html');
    if ($html === false) throw new RuntimeException('template');
    // POST fallback is served under /api/: assets must resolve from the site root.
    $html=str_replace(['href="./style.css"','src="./form.js"','href="./favicon.svg"'],['href="/style.css"','src="/form.js"','href="/favicon.svg"'],$html);
    foreach ($values as $name=>$value) {
        if ($name==='concerns' && is_array($value)) {
            foreach ($value as $choice) $html=preg_replace_callback('/<input\b[^>]*name="concerns"[^>]*>/',fn($m)=>str_contains($m[0],'value="'.sr_escape((string)$choice).'"')?substr($m[0],0,-1).' checked>':$m[0],$html);
            continue;
        }
        if (!is_string($value)) continue;
        $html=preg_replace_callback('/<input\b[^>]*name="'.preg_quote($name,'/').'"[^>]*>/',function($m)use($value){
            $tag=$m[0];
            if (str_contains($tag,'type="radio"') || str_contains($tag,'type="checkbox"')) return str_contains($tag,'value="'.sr_escape($value).'"')?substr($tag,0,-1).' checked>':$tag;
            $tag=preg_replace('/\svalue="[^"]*"/','',$tag);
            return substr($tag,0,-1).' value="'.sr_escape($value).'">';
        },$html);
        if ($name==='other_concern') $html=preg_replace_callback('/(<textarea\b[^>]*name="other_concern"[^>]*>).*?(<\/textarea>)/s',fn($m)=>$m[1].sr_escape($value).$m[2],$html);
    }
    if ($errors) {
        $message=implode(' ',array_values($errors));
        $html=preg_replace('/<p id="errors"[^>]*>.*?<\/p>/s','<p id="errors" class="errors" role="alert" tabindex="-1">'.sr_escape($message).'</p>',$html);
        // Validation response remains a plain usable form, including for JS clients
        // that happen to navigate with a normal POST.
        $html=str_replace('<script src="/form.js" defer></script>','',$html);
    }
    if ($success) {
        $html=preg_replace('/<form\b.*?<\/form>/s','',$html);
        $html=str_replace('data-step="6" hidden','data-step="6"',$html);
        $html=str_replace('<script src="/form.js" defer></script>','',$html);
    }
    return $html;
}
function sr_reply(int $status, array $result, array $values=[], array $errors=[]): void {
    http_response_code($status);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=utf-8'); echo json_encode($result,JSON_THROW_ON_ERROR);
    } else { header('Content-Type: text/html; charset=utf-8'); echo sr_render($values,$errors,($result['ok']??false)===true); }
}
function sr_insert(PDO $pdo, array $v): void {
    $uuid = bin2hex(random_bytes(16));
    $id=substr($uuid,0,8).'-'.substr($uuid,8,4).'-4'.substr($uuid,13,3).'-a'.substr($uuid,17,3).'-'.substr($uuid,20,12);
    $columns=['id','contact_name','business_name','email','phone','employee_range','renewal_window','concerns','other_concern','outside_target_size','utm_source','utm_medium','utm_campaign','utm_content','fbclid'];
    $args=[$id,$v['name'],$v['business_name'],$v['email'],$v['phone']?:null,$v['staff_size'],$v['renewal'],json_encode($v['concerns'],JSON_THROW_ON_ERROR),$v['other_concern']?:null,in_array($v['staff_size'],['1–4','50+'],true)?1:0];
    foreach (['utm_source','utm_medium','utm_campaign','utm_content','fbclid'] as $key) $args[]=$v[$key]?:null;
    $sql='INSERT INTO switch_review_leads ('.implode(',',$columns).",consent_at,created_at,status) VALUES (".implode(',',array_fill(0,count($columns),'?')).",UTC_TIMESTAMP(3),UTC_TIMESTAMP(3),'new')";
    $pdo->prepare($sql)->execute($args); // No SELECT, lastInsertId lookup, or schema probes.
}
function sr_main(): void {
    sr_headers();
    try {
        $config=sr_config();
        $origin=$config['PUBLIC_ORIGIN']??'';
        if (!preg_match('~^https://[a-z0-9.-]+$~D',$origin)) throw new RuntimeException('origin_config');
        $remote=$_SERVER['REMOTE_ADDR']??'';
        $trusted=array_filter(array_map('trim',explode(',',$config['TRUSTED_PROXIES']??'')));
        $proxy=in_array($remote,$trusted,true);
        $secure=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || ($proxy && ($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
        if (!$secure) { header('Location: '.$origin.'/',true,308); return; }
        $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
        if (($_SERVER['REQUEST_METHOD']??'GET')==='GET' && in_array($path,['/','/index.html','/switch-review','/switch-review/'],true)) {
            $v=[];foreach(['utm_source','utm_medium','utm_campaign','utm_content','fbclid'] as $key) $v[$key]=is_string($_GET[$key]??null)?substr($_GET[$key],0,2000):'';
            header('Content-Type: text/html; charset=utf-8'); echo sr_render($v); return;
        }
        if ($path!=='/api/switch-review') { sr_reply(404,['ok'=>false],[],['form'=>'Page not found.']); return; }
        if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST');sr_reply(405,['ok'=>false],[],['form'=>'Use the review form to submit.']);return; }
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>10240) { sr_reply(413,['ok'=>false],[],['form'=>'Submission is too large.']); return; }
        if (($_SERVER['HTTP_ORIGIN']??$origin)!==$origin) { sr_reply(403,['ok'=>false],[],['form'=>'Submit from the review page.']);return; }
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/x-www-form-urlencoded') { sr_reply(415,['ok'=>false],[],['form'=>'Unsupported form format.']); return; }
        $body=file_get_contents('php://input',false,null,0,10241);
        if ($body===false || strlen($body)>10240) { sr_reply(413,['ok'=>false],[],['form'=>'Submission is too large.']);return; }
        try { $raw=sr_parse($body); } catch (InvalidArgumentException $e) { sr_reply(400,['ok'=>false,'errors'=>['form'=>'Invalid form fields.']],[],['form'=>'Invalid form fields.']);return; }
        $ip=$remote;
        if ($proxy) {
            $chain=array_merge(array_map('trim',explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']??'')),[$remote]);
            for($i=count($chain)-1;$i>=0;$i--) if(!in_array($chain[$i],$trusted,true)) { $ip=filter_var($chain[$i],FILTER_VALIDATE_IP)?$chain[$i]:$remote;break; }
        }
        if (!sr_rate($config['RATE_DIR']??'', $config['RATE_SECRET']??'', $ip,time())) { header('Retry-After: 3600');sr_reply(429,['ok'=>false],$raw,['form'=>'Too many requests. Please try later or call 409-937-2798.']);return; }
        if (sr_honeypot($raw)) { sr_reply(200,['ok'=>true]);return; }
        [$values,$errors]=sr_validate($raw);
        if ($errors) { sr_reply(422,['ok'=>false,'errors'=>$errors],$values,$errors);return; }
        foreach(['DB_HOST','DB_NAME','DB_USER','DB_PASSWORD'] as $key) if(empty($config[$key])) throw new RuntimeException('db_config');
        $host=$config['DB_HOST'];$db=$config['DB_NAME'];$port=(int)($config['DB_PORT']??3306);
        if(preg_match('/[;\r\n]/',$host.$db)) throw new RuntimeException('db_config');
        $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
        if(!empty($config['DB_SSL_CA'])) $options[PDO::MYSQL_ATTR_SSL_CA]=$config['DB_SSL_CA'];
        $pdo=new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",$config['DB_USER'],$config['DB_PASSWORD'],$options);
        sr_insert($pdo,$values); sr_reply(201,['ok'=>true]);
    } catch (Throwable $e) {
        error_log('[switch-review] request_failed'); // No body, env values, SQL, or exception message.
        sr_reply(503,['ok'=>false],[],['form'=>'We could not save your review. Please try later or call 409-937-2798.']);
    }
}
if (!defined('SWITCH_REVIEW_LIBRARY')) sr_main();
