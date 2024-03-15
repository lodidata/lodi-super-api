<?php
namespace Model;
use DB;
class CommonArea extends \Illuminate\Database\Eloquent\Model {

    protected $connection = 'common';

    protected $table = 'area';

    public $timestamps = false;

    protected $fillable = [
                            'code_id',
                            'parent_id',
                            'name',
                            'status',
                        ];
                          
    public static function boot() {
        parent::boot();
    }

    /**
     * 返回国家
     *
     * @param number $country_id 国家id
     */
    public static function getCountry($countryId = 1) {
        return DB::resultToArray(self::where('code_id', $countryId)->selectRaw('code_id as value, parent_id as parent')->get()->toArray());
    }

    /**
     * 返回国家列表
     */
    public static function getCountryList() {
        return DB::resultToArray(self::where('parent_id', 0)->selectRaw('code_id as value, parent_id as parent, name')->get()->toArray());
    }

    /**
     * 返回省份列表
     *
     * @param number $country_id 国家id
     */
    public static function getProvinceList($countryId = 1)
    {
        return DB::resultToArray(self::where('parent_id', $countryId)->selectRaw('code_id as value, parent_id as parent, name')->get()->toArray());
    }

    /**
     * 返回市列表
     *
     * @param number $province_id 省份id
     */
    public static function getCityList($provinceId = 11)
    {
        return DB::resultToArray(self::where('parent_id', $provinceId)->selectRaw('code_id as value, parent_id as parent, name')->get()->toArray());
    }

    /**
     * 获取地区列表
     *默认中国北京东城区
     *
     * @return array
     */
    public static function getArea()
    {
        $countryId = 1;
        $country  = self::getCountryList($countryId);
        $province = self::getProvinceList($countryId);
        $city = DB::resultToArray(self::where('parent_id', '>', 1)->where('status', 1)->selectRaw('code_id as value, parent_id as parent, name')->get()->toArray());
        $area = array_merge($country, $province);
        $area = array_merge($area, $city);
        return $area;
    }

    /**
     * 依据城市代号获取地区
     * @param  $code 城市代号
     *
     * @return array
     */
    public static function getAreaInfo($code)
    {
            $city = self::where('code_id', '=', $code)->first(['code_id as value', 'parent_id as parent', 'name']);
            if($city) {
                $city = $city->toArray();
                $parent = $city['parent'] ?? 0;
                $city['area'] = $city['name'] ?? '';
                while ($parent) {
                    $tmp = self::where('code_id', '=', $parent)->first(['code_id as value', 'parent_id as parent', 'name'])->toArray();
                    if ($tmp) {
                        $city['area'] = $tmp['name'] . $city['area'];
                        $parent = $tmp['parent'] ?? 0;
                    }
                }
            }
        return $city;
    }
}


