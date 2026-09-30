<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function finance_user_count(): int
{
    try {
        return (int) db_finance()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
}

function login_user(string $username, string $password): bool
{
    ensure_connected_system_schema();
    $db=db_finance();
    $stmt=$db->prepare('SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.username=? AND u.is_active=1 LIMIT 1');
    $stmt->execute([$username]);$user=$stmt->fetch();

    // Normal Finance account remains the first authentication source.
    if($user && (empty($user['locked_until']) || strtotime((string)$user['locked_until'])<=time())
       && password_verify($password,(string)$user['password_hash'])){
        $db->prepare('UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['last_activity']=time();
        audit('login','auth','user',(string)$user['id']);return true;
    }

    // HRMS and POS staff use the SAME username/password. Finance only creates
    // a local permission/profile shadow; passwords are verified against source.
    foreach(['hrms','pos'] as $system){
        try{
            $srcdb=$system==='hrms'?db_hrms():db_pos();
            if($system==='hrms'){
                $q=$srcdb->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND status='active' LIMIT 1");
            }else{
                $q=$srcdb->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND is_active=1 LIMIT 1");
            }
            $q->execute([$username,$username]);$src=$q->fetch();
            if(!$src) continue;
            $hash=(string)($src['password']??'');
            if($hash==='' || !password_verify($password,$hash)) continue;
            if(!finance_role_for_external($system,(string)($src['role']??''))) continue;
            $fid=upsert_external_finance_user($system,$src); if(!$fid) continue;
            $db->prepare('UPDATE users SET last_login_at=NOW(),failed_attempts=0,locked_until=NULL WHERE id=?')->execute([$fid]);
            session_regenerate_id(true);$_SESSION['user_id']=$fid;$_SESSION['last_activity']=time();
            audit('login','auth','external_identity',(string)$fid,null,['source_system'=>$system]);return true;
        }catch(Throwable){ /* Finance local login remains usable if a source DB is offline. */ }
    }

    if($user){
        $attempts=(int)$user['failed_attempts']+1;$locked=$attempts>=5?date('Y-m-d H:i:s',time()+900):null;
        $db->prepare('UPDATE users SET failed_attempts=?,locked_until=? WHERE id=?')->execute([$locked?0:$attempts,$locked,(int)$user['id']]);
    }
    usleep(250000);return false;
}

function logout_user(): void
{
    if (current_user()) {
        audit('logout', 'auth', 'user', (string) current_user()['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}