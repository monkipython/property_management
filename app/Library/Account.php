<?php
namespace App\Library;
use Illuminate\Support\Facades\Storage;
use App\Library\{Helper, Html};
use App\Http\Models\Account\Account AS M;
class Account{
  public static function getHtmlInfo($account){
    # HEADER
    $span   = Html::i('', ['class'=>'fa fa-fw fa-user']) . ' ' . Html::span($account['firstname'] . ' ' . $account['lastname'], ['class'=>'hidden-xs']);
    $header = Html::a($span, ['href'=>'#', 'class'=>'dropdown-toggle', 'data-toggle'=>'dropdown']);
    
    # BODY
    $body = Html::li('<p>
        Alexander Pierce - Web Developer
        <small>Member since Nov. 2012</small>
      </p>', ['class'=>'user-header']);
    
    # FOOTER
    $a       = Html::a('Profile', ['href'=>'/profile', 'class'=>'btn btn-default btn-flat']);
    $profile = Html::div($a, ['class'=>'pull-left']);
    
    $a       = Html::a('Sign out', ['href'=>'/logout', 'class'=>'btn btn-default btn-flat']);
    $logout  = Html::div($a, ['class'=>'pull-right']);
    $footer  = Html::li($profile . $logout, ['class'=>'user-footer']);
    
    return $header . Html::ul($body .$footer, ['class'=>'dropdown-menu']);
  }
//------------------------------------------------------------------------------
  public static function getHtmlSkill($skill){
    $skills = explode(',', $skill);
    $data = '';
    foreach($skills as $v){
      $data .= Html::span($v, ['class'=>'label label-danger']) . ' '; 
    }
    return $data;
  }
}