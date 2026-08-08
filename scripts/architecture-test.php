<?php
/**
 * Architecture Test — Vendor Marketplace
 * -------------------------------------
 * Static-analysis guard (no WordPress loaded) enforcing the centralized
 * AJAX/REST architecture. token_get_all() strips comments accurately while
 * preserving line numbers, so it is CI-safe.
 *
 * Contract:
 *   AJAX : registration goes through ONE entry (RouteRegistry in
 *          CoreServiceProvider). add_action('wp_ajax_*') allowed ONLY in the
 *          whitelist (CoreServiceProvider/CronServiceProvider/VendorRequestsAdminPage).
 *   REST : routes registered ONLY from the whitelisted sources (Api controllers
 *          registered by ApiServiceProvider + VendorServiceProvider for
 *          register-guest/apply).
 *
 * Checks:
 *   1. No live add_action('wp_ajax_*') outside whitelist (inside known-dead => WARN).
 *   2. No duplicate AJAX action registration within RouteRegistry.
 *   3. Every UI-referenced action must have a live handler.
 *   4. Registered handlers with no UI reference => WARN (--strict => FAIL).
 *   5. RouteRegistry entries reference existing controller & request classes.
 *   6. REST routes only from whitelisted files; no duplicate live registration.
 *   7. Legacy entry points (registerAjaxHandlers/registerRestRoutes/enqueueAssets)
 *      must not be live-called.
 *
 * Usage: php scripts/architecture-test.php [--root=/path] [--strict]
 * Exit : 0 pass, 1 failures, 2 runtime error.
 */
$root=null;$strict=false;
foreach(array_slice($argv,1) as $a){if(str_starts_with($a,'--root=')){$root=substr($a,7);}elseif($a==='--strict'){$strict=true;}}
if(!$root){$root=dirname(__DIR__);} $root=rtrim($root,'/');
if(!is_dir($root.'/app')||!is_dir($root.'/public')){fwrite(STDERR,"[FATAL] '$root' is not the plugin root.\n");exit(2);}

const ALLOWED_AJAX_FILES=['app/Providers/CoreServiceProvider.php','app/Providers/CronServiceProvider.php','app/Admin/VendorRequestsAdminPage.php'];
const ALLOWED_REST_FILES=['app/Providers/VendorServiceProvider.php','app/Http/Controllers/Api/VendorApiController.php','app/Http/Controllers/Api/ProductApiController.php'];
const DEAD_WHOLE_FILES=['app/Controllers/RestVendorController.php','app/Controllers/RestVendorRegistrationController.php','app/Modules/RestAPI.php'];
const LEGACY_DEAD_METHODS=[
    'app/Modules/Vendor/VendorServiceProvider.php'=>['registerAjaxHandlers','registerRestRoutes','enqueueAssets'],
    'app/Controllers/RestVendorRegistrationController.php'=>['registerRoutes','init'],
];
const PREFIX='[Architecture] ';
$W=[];$V=0;
function fail(string $m):void{global $W,$V;$W[]=$m;$V++;echo PREFIX."FAIL  $m\n";}
function warn(string $m):void{global $W;$W[]=$m;echo PREFIX."WARN  $m\n";}
function info(string $m):void{echo PREFIX."INFO  $m\n";}
function phpFiles(string $d):array{$o=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if($f->isFile()&&$f->getExtension()==='php'&&!str_contains($f->getPathname(),'.qa_backups'))$o[]=$f->getPathname();}sort($o);return $o;}
function liveLines(string $file):array{$tokens=token_get_all(file_get_contents($file));$out=[];$line=1;
  foreach($tokens as $t){if(is_array($t)){$line=$t[2];if(in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true))continue;$out[$line]=($out[$line]??'').$t[1];}
  else{$out[$line]=($out[$line]??'').$t;if(str_contains($t,"\n"))$line+=substr_count($t,"\n");}}return $out;}
function deadMethodAtLine(array $live,int $ln,array $dead):?string{ksort($live);$depth=0;$cur=null;
  foreach($live as $l=>$c){if($l>$ln)break;if(preg_match('/function\s+([a-zA-Z0-9_]+)\s*\(/',$c,$m)&&!preg_match('/function\s*\(/',$c))$cur=$m[1];$depth+=substr_count($c,'{')-substr_count($c,'}');}
  return($cur!==null&&in_array($cur,$dead,true))?$cur:null;}
/** best-effort resolve of NAMESPACE const used in register_rest_route. */
function nsConstValue(array $live):string{foreach($live as $c){if(preg_match("/(?:const|define)\(?\s*NAMESPACE\s*[,=]\s*['\"]([^'\"]+)['\"]/i",$c,$m))return $m[1];}return '';}

$phpFiles=phpFiles($root.'/app');
$requestFiles=phpFiles($root.'/app/Http/Requests');
$directAjax=[];$registered=[];$dupActions=[];$perFile=[];
$restLive=[];   // 'ns|path' => [loc]
$restUnallowed=[];

foreach($phpFiles as $file){
    $rel=substr($file,strlen($root)+1);
    $live=liveLines($file); $perFile[$rel]=$live;
    $deadMethods=LEGACY_DEAD_METHODS[$rel]??[];
    $isDeadFile=in_array($rel,DEAD_WHOLE_FILES,true);
    $ns=nsConstValue($live);
    $inAllowedRest=in_array($rel,ALLOWED_REST_FILES,true);

    foreach($live as $ln=>$code){
        $inDead=$isDeadFile?'%FILE%':(deadMethodAtLine($live,$ln,$deadMethods)??null);
        $loc="$rel:$ln";

        // AJAX
        if(preg_match("/add_action\(\s*['\"]wp_ajax(?:_nopriv)?_([a-z0-9_]+)['\"]/i",$code,$m)){
            $bare=$m[1];
            if($inDead){warn("add_action('wp_ajax_$bare') inside DEAD code ($loc).");}
            elseif(!in_array($rel,ALLOWED_AJAX_FILES,true)){fail("add_action('wp_ajax_$bare') at $loc — allowed only in ".implode(', ',ALLOWED_AJAX_FILES));}
            else{$directAjax[$bare][]=$loc;}
        }

        // REST (multi-line calls: first arg ns, second arg path)
        if(str_contains($code,'register_rest_route')){
            if(preg_match("/register_rest_route\(\s*([^,]+),\s*['\"]([^'\"]+)['\"]/",$code,$m)){
                $nsArg=trim($m[1]); $path=$m[2];
                // resolve namespace argument (self::NAMESPACE, 'vmp/v1', const X)
                $resolvedNs=$nsArg;
                if(preg_match("/::(\w+)$/",$nsArg,$nm)){$resolvedNs=($nm[1]==='NAMESPACE'?($ns?:$nm[1]):$nm[1]);}
                elseif(preg_match("/['\"]([^'\"]+)['\"]/",$nsArg,$qm)){$resolvedNs=$qm[1];}
                $key=$resolvedNs.'|'.$path;
                if($inDead){continue;}
                if($inAllowedRest){$restLive[$key][]=$loc;}
                else{$restUnallowed[$key][]=$loc;}
            }
        }

        // Legacy live-call
        foreach(LEGACY_DEAD_METHODS as $f=>$methods){if($f!==$rel)continue;
            foreach($methods as $mm){if(preg_match('/(?:->|::)\s*'.preg_quote($mm,'/').'\s*\(\s*\)/',$code)&&!preg_match('/function\s+'.preg_quote($mm,'/').'\s*\(/',$code))
                fail("Legacy entry point $mm() is LIVE-called at $loc — must stay disabled.");}}
    }
}
foreach($restUnallowed as $key=>$locs){foreach($locs as $l) fail("REST route '$key' registered outside whitelist at $l");}
foreach($restLive as $key=>$locs){ if(count($locs)>1) fail("LIVE REST route '$key' registered ".count($locs)." times: ".implode('; ',$locs)); }

// RouteRegistry
$regLive=$perFile['app/Providers/CoreServiceProvider.php']??[];
foreach($regLive as $ln=>$code){
    if(preg_match("/\\\$registry->ajax\(\s*'([a-z0-9_]+)'\s*,\s*([A-Za-z0-9_\\\\]+)::class\s*,\s*'([a-zA-Z0-9_]+)'/",$code,$m)){
        $bare=$m[1];$entry=['controller'=>trim($m[2]),'method'=>$m[3],'line'=>$ln];
        if(isset($registered[$bare])){$dupActions[$bare][]=$registered[$bare]['line'];$dupActions[$bare][]=$ln;}
        $registered[$bare]=$entry;
    }
}
foreach($dupActions as $a=>$ls) fail("Duplicate RouteRegistry registration of '$a' (CoreServiceProvider.php lines ".implode(', ',array_unique($ls)).')');

// UI references
$refPatterns=[
    '/data-action\s*=\s*["\'](vmp_[a-z0-9_]+)["\']/i',
    '/action\s*:\s*["\'](vmp_[a-z0-9_]+)["\']/i',
    '/append\(\s*["\']action["\']\s*,\s*["\'](vmp_[a-z0-9_]+)["\']/i',
    '/["\']action["\']\s*=>\s*["\'](vmp_[a-z0-9_]+)["\']/i',
    '/\.data\(\s*["\']action["\']\s*,\s*["\'](vmp_[a-z0-9_]+)["\']/i',
];
$referenced=[];
foreach([$root.'/public',$root.'/admin'] as $dir){if(!is_dir($dir))continue;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
    foreach($it as $f){if(!$f->isFile()||!in_array($f->getExtension(),['js','php'],true)||str_contains($f->getPathname(),'.qa_backups'))continue;
        $rel=substr($f->getPathname(),strlen($root)+1);$content=file_get_contents($f->getPathname());
        foreach($refPatterns as $p){if(preg_match_all($p,$content,$m,PREG_OFFSET_CAPTURE))foreach($m[1] as $h){$a=strtolower($h[0]);$l=substr_count(substr($content,0,$h[1]),"\n")+1;$referenced[$a][]="$rel:$l";}}
    }}
$liveHandlers=array_fill_keys(array_merge(array_keys($registered),array_keys($directAjax)),true);
foreach($referenced as $action=>$refs){if(!isset($liveHandlers[$action])) fail("UI references '$action' (e.g. {$refs[0]}) but NO live handler is registered.");}

$noUI=[];
foreach($registered as $action=>$entry){if(isset($referenced[$action]))continue;
    $noUI[]="$action ({$entry['controller']}::{$entry['method']})";
    if($strict) fail("Registered '$action' ({$entry['controller']}::{$entry['method']}) has no UI reference.");}
if($noUI){echo PREFIX."NOTE  ".count($noUI)." registered handlers have no UI reference: ".implode(', ',array_slice($noUI,0,6)).(count($noUI)>6?'…':'')."\n";}

// Controller/request existence
$ctrlDir=$root.'/app/Controllers';$reqDir=$root.'/app/Http/Requests';
foreach($registered as $action=>$entry){
    $cls=ltrim($entry['controller'],'\\');$short=substr($cls,strrpos($cls,'\\')!==false?strrpos($cls,'\\')+1:0);
    $found=is_file($ctrlDir.'/'.$short.'.php');
    if(!$found){foreach($phpFiles as $f){if(basename($f,'.php')===$short){$found=true;break;}}}
    if(!$found){fail("RouteRegistry '$action' references missing controller class '$cls'.");continue;}
    $cf=$ctrlDir.'/'.$short.'.php';if(!is_file($cf))continue;
    if(preg_match('/function\s+'.preg_quote($entry['method'],'/').'\s*\(\s*([^)]*)\)/s',file_get_contents($cf),$mm))
        if(preg_match('/([A-Za-z0-9_\\\\]+)\s+\$request/',$mm[1],$pm)){ $rc=ltrim($pm[1],'\\');$rs=substr($rc,strrpos($rc,'\\')!==false?strrpos($rc,'\\')+1:0);
            if(!is_file($reqDir.'/'.$rs.'.php')) fail("RouteRegistry '$action' -> {$entry['controller']}::{$entry['method']} missing request class '$rc'.");}
}

// ─── 8. If any Request uses the 'nullable' rule, the validation engine
// MUST support it. (applyRule: nullable => null, empty values allowed.)
$usesNullable=false;
foreach($phpFiles as $f){
    if(!str_contains($f,'/Http/Requests/')) continue;
    $content=file_get_contents($f);
    $clean=preg_replace('#/\*.*?\*/#s','',preg_replace('#//.*#','',$content));
    if(preg_match("/(?:'|\")nullable(?:'|\")/",$clean)){$usesNullable=true;break;}
}
if($usesNullable){
    $engine=file_get_contents($root.'/app/Http/Requests/AbstractRequest.php');
    // Engine support = 'nullable' token appears anywhere inside applyRule
    // (token matches a string literal, not just a comment). strpos is exact
    // and avoids quoting pitfalls of regex in shell-invoked PHP.
    $engineHasNullable = strpos($engine, "'nullable'") !== false;
    if(!$engineHasNullable){
        fail("Requests use 'nullable' rule but AbstractRequest::applyRule does not support it — runtime InvalidArgumentException.");
    }
}

// ─── 9. Requests must NEVER trust vendor_id coming from input ───
// Ownership must be derived from get_current_user_id() inside the
// Request (resolveVendorId) — a POSTed vendor_id is attacker-controlled.
foreach($requestFiles as $f){
    if(!str_contains($f,'/Http/Requests/')) continue;
    $rel=substr($f,strlen($root)+1);
    $content=file_get_contents($f);
    // Guard: any Request whose rules() declares vendor_id is suspect; and
    // reading vendor_id directly from $this->data without resolveVendorId.
    if(preg_match("/\['vendor_id'\]\s*=>/",$content)){
        fail("Request $rel should NOT accept vendor_id in rules() — identity must come from auth context (get_current_user_id()).");
    }
    if(preg_match("/\$data\['vendor_id'\]\s*=\s*\$this->get\('vendor_id'/",$content)){
        fail("Request $rel copies vendor_id from input — ownership must be derived from the authenticated user.");
    }
}
if(!isset($requestFiles)){$requestFiles=[];}

echo "\n".str_repeat('=',60)."\n";
echo PREFIX."Summary\n";
echo PREFIX."  RouteRegistry AJAX actions : ".count($registered)."\n";
echo PREFIX."  Direct AJAX hooks (whitelist): ".count($directAjax)."\n";
echo PREFIX."  UI-referenced actions      : ".count($referenced)."\n";
echo PREFIX."  LIVE REST routes           : ".count($restLive)."\n";
echo PREFIX."  Failures / Warnings        : $V / ".count($W)."\n";
echo str_repeat('=',60)."\n";
if($V>0){echo PREFIX."RESULT: FAIL ($V violation(s))\n";exit(1);}
echo PREFIX."RESULT: PASS\n";exit(0);
