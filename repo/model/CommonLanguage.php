<?php
namespace Model;
use DB;
class CommonLanguage extends \Illuminate\Database\Eloquent\Model {

    protected $connection = 'common';

    protected $table = 'language';

    public $timestamps = false;


}


