<?php
namespace Utils;

/**
 * 验证码工具类
 */
class ValidateCode
{
    private $code;
    private $image;
    private $isDrawLine;
    private $lineCount;
    private $isDrawPoint;
    private $pointCount;
    private $width;
    private $height;
    private $fontSize;
    private $factor;

    /**
     * 构造方法
     * @param array $params 验证码参数。
     * width: 图片宽度， 默认：100；
     * height: 图片高度， 默认：30；
     * bgColor: 图片背景颜色， 默认：[255, 255, 255]；
     * drawLine: 是否生成干扰线，默认：true；
     * drawPoint: 是否生成干扰点， 默认： true；
     * lineCount: 干扰线数量， 默认： 2；
     * pointCount: 干扰点数量，默认：300；
     * fontSize: 字体大小， 取值：1 - 5；
     * factor：因子， 默认： 0123456789；
     * @example:  new ValidateCodeUtils()
     * @example: new ValidateCodeUtils(['bgColor'=>[255,0,0], 'drawLine'=>false))
     */
    function __construct(array $params = [])
    {
        $this->width = $params['width'] ?? 100;
        $this->height = $params['height'] ?? 30;
        $this->image = imagecreatetruecolor($this->width, $this->height);

        $bgColor = $params['bgColor'] ?? [255, 255, 255];
        $this->isDrawLine = $params['drawLine'] ?? true;
        $this->lineCount = $params['lineCount'] ?? 2;
        $this->isDrawPoint = $params['drawPoint'] ?? true;
        $this->pointCount = $params['pointCount'] ?? 300;
        $this->fontSize = $params['fontSize'] ?? 5;
        $this->factor = $params['factor'] ?? '0123456789';

        $bgColor = imagecolorallocate($this->image, $bgColor[0], $bgColor[1], $bgColor[2]);
        imagefill($this->image, 0, 0, $bgColor);
    }

    function __destruct()
    {
        isset($this->image) && imagedestroy($this->image);
    }

    /**
     * 生成验证码图片
     * @param int $length 验证码长度
     * @return  image resource
     */
    public function create(int $length = 4)
    {
        $this->buildCode($length);
        $fontColor = imagecolorallocate($this->image, rand(0, 120), rand(0, 120), rand(0, 120));

        $h = $this->height / 2;
        $w = $this->width / $length;
        for ($i = 0; $i < $length; $i++) {
            $x = ($i * $w) + rand(5, 10);
            $y = rand(5, $h);
            imagestring($this->image, $this->fontSize, $x, $y, $this->code{$i}, $fontColor);
        }

        $this->isDrawLine && $this->drawLine();
        $this->isDrawPoint && $this->drawPoint();

        return $this->image;
    }

    /**
     * 随机生成验证码
     * @param int $length 验证码长度
     */
    private function buildCode(int $length)
    {
        $len = strlen($this->factor);
        for ($i = 0; $i < $length; $i++) {
            $this->code .= substr($this->factor, rand(0, $len) - 1, 1);
        }
    }

    /**
     * 生成干扰线
     */
    private function drawLine()
    {
        for ($i = 0; $i < $this->lineCount; $i++) {
            $lineColor = imagecolorallocate($this->image, rand(80, 220), rand(80, 220), rand(80, 220));
            imageline($this->image, rand(1, $this->width), rand(1, $this->height), rand(1, $this->width),
                rand(1, $this->height), $lineColor);
        }
    }

    /**
     * 生成干扰点
     */
    private function drawPoint()
    {
        for ($i = 0; $i < $this->pointCount; $i++) {
            $pointColor = imagecolorallocate($this->image, rand(50, 120), rand(50, 120), rand(50, 120));
            imagesetpixel($this->image, rand(1, $this->width), rand(1, $this->width), $pointColor);
        }
    }

    /**
     * 获取验证码
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }
}