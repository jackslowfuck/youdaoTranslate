<?php
/**
 * 有道api翻译 美股的新闻标题和内容
 *  版本：1.1，请求方式：get，编码方式：utf-8
           主要功能：中英互译，同时获得有道翻译结果和有道词典结果（可能没有）    
           参数说明：    
        　type - 返回结果的类型，固定为data    
        　doctype - 返回结果的数据格式，xml或json或jsonp   
        　version - 版本，当前最新版本为1.1     
        　q - 要翻译的文本，必须是UTF-8编码，字符长度不能超过200个字符，需要进行urlencode编码    
        　only - 可选参数，dict表示只获取词典数据，translate表示只获取翻译数据，默认为都获取    
        　注： 词典结果只支持中英互译，翻译结果支持英日韩法俄西到中文的翻译以及中文到英语的翻译     
    errorCode：    
        　0 - 正常     
        　20 - 要翻译的文本过长     
        　30 - 无法进行有效的翻译    
        　40 - 不支持的语言类型    
        　50 - 无效的key    
        　60 - 无词典结果，仅在获取词典结果生效
            
 * 数据接口：http://fanyi.youdao.com/openapi.do?keyfrom=<keyfrom>&key=<key>&type=data&doctype=<doctype>&version=1.1&q=要翻译的文本
 * 使用API key 时，请求频率限制为每小时1000次，超过限制会被封禁。
 * API key：1234567890 keyfrom：secret
 */
class TranslateController extends Cms_Controller_Base
{
    private $_api = 'http://fanyi.youdao.com/openapi.do?keyfrom=thstranslate1&key=1234567890&type=data&';
    private $_errorMsg = array(
        20 => '要翻译的文本过长', 
        30 => '无法进行有效的翻译', 
        40 => '不支持的语言类型',
        50 => '无效的key'
    );
    private $_wordsNums = 15; //要翻译的单词数量 太多会容易超过200字符的限制
	public function translateAction()
	{
	    //error_reporting(E_ALL);
	    //ini_set('display_errors', true);
		$newsid = $this->_request->getParam('newsid');
		$selector = $this->_request->getParam('selector');
		$originContent = $this->_request->getParam('content');
		$contentWithHtml = '';
		if ($selector == 'DataUeditor') {
		  $contentWithHtml = $this->_request->getParam('contentWithHtml');
		}
		if (!$originContent) {
			echo json_encode(array(
			    'selector' => $selector . $newsid, 
			    'translation' => array('content is invalid'))
			);
			exit;
		}
		
		//
		$tool = new Hexinlib_Io_File_Common();
		$url = '';
		$parameters = array(
		    'version' => '1.1',
		    'only' => 'translate',
		    'doctype' => 'json'
		);
		//echo $content;exit;
		switch ($selector) {
		    //title 一般小于200字符
			case 'title':
			    //$parameters['q'] = $content;
			    $content = $this->_handleContent($originContent);
			    $url = $this->_api . http_build_query($parameters) . '&q=' . $content;
			    $res = $tool->getWebContent($url);
			    echo $this->_fmtData($res, $selector, $newsid, $originContent, $url);
			    exit;
			    break;
			case 'DataUeditor':
// 			    echo json_encode(array(
// 			    'selector' => $selector . $newsid, //mb_strlen($content, 'utf-8')
// 			    'translation' => array(str_word_count($content)))
// 			);
//                 exit;
                $allWords = str_word_count($originContent);
                $return = '';
                if ($allWords <= $this->_wordsNums) {
                    $content = $this->_cutStrByWords($originContent, 0, $this->_wordsNums);
                    $content = $this->_handleContent($content);
                    $url = $this->_api . http_build_query($parameters) . '&q=' . $content;
                    $res = $tool->getWebContent($url);
                    echo $this->_fmtData($res, $selector, $newsid, $contentWithHtml, $url);
                } else {
                    //整篇文章按一定数量单词来分隔 翻译
                	//大于15个单词的文章 有 floor($allWords/$this->_wordsNums)个整份 
                	//和($allWords%$this->_wordsNums)个单词
                	$cnt = floor($allWords / $this->_wordsNums);
                	$left = $allWords % $this->_wordsNums;
                	for ($i = 0; $i <= $cnt; $i++) {
                	    $offset = $i * $this->_wordsNums;
                	    if ($i == $cnt) {
                	        $content = $this->_cutStrByWords($originContent, $offset, $left);
                	    } else {
                	       $content = $this->_cutStrByWords($originContent, $offset, $this->_wordsNums);
                	    }
                	    $content = $this->_handleContent($content);
                	    $url = $this->_api . http_build_query($parameters) . '&q=' . $content;
                	    $res = $tool->getWebContent($url);
                	    $return .= $this->_getTranslationSlice($res);
                	}
                	echo $this->_fmtData($res, $selector, $newsid, $contentWithHtml, $url, $return);
                }
			    exit;
			    break;
		}
	}
	private function _getTranslationSlice($json)
	{
		if ($json) {
			$tmp = json_decode($json, true);
			if (isset($tmp['translation'][0])) {
				return $tmp['translation'][0];
			} else {
			    //有省略代表翻译异常
				return '...';
			}
		}
	}
	/**
	 * 截取一段英文的单词
	 * @param string $string 输入的英文语句
	 * @param int $word_limit 单词个数
	 * @param int $offset 类似分页的跳过多少截取多少
	 * @return string
	 */
	private function _cutStrByWords($string, $offset, $wordlimit)
	{
	    $words = explode(" ", $string);
	    return implode(" ", array_splice($words, $offset, $wordlimit));
	}
	
	public function _handleContent($originContent)
	{
	    if (mb_detect_encoding($originContent) == 'GBK') {
	        $content = $this->_myUrlEncode(iconv('gbk', 'utf-8', $originContent));
	    } else {
	        $content = $this->_myUrlEncode($originContent);
	    }
	    return $content;
	}
	/**
	 * urlencode function and rawurlencode are mostly based on RFC 1738.
     * However, since 2005 the current RFC in use for URIs standard is RFC 3986.
     * Here is a function to encode URLs according to RFC 3986.  
	 */
	private function _myUrlEncode($string)
	{
	    $string = str_replace(
	        array('%', '&', '*', '#'), 
	        array(' percent ', '', '', ''), 
	        $string);
        $entities = array(
            '%21', '%2A', '%27', '%28', 
            '%29', '%3B', '%3A', '%40', 
            '%26', '%3D', '%2B', '%24', 
            '%2C', '%2F', '%3F', '%25', 
            '%23', '%5B', '%5D'
        );
        $replacements = array(
            '!', '*', "'", "(", ")", 
            ";", ":", "@", "&", "=", 
            "+", "$", ",", "/", "?", 
            "%", "#", "[", "]"
        );
        return str_replace($entities, $replacements, urlencode($string));
    }
    /**
     * 最终返回的json 可以扩充需要的参数
     * @param json $json
     * @param string $selector
     * @param int $newsid
     * @param string $originContent
     * @param string $url
     * @return string
     */
	private function _fmtData($json, $selector, $newsid, $content, $url = '', $extra = '')
	{
	   $return = '';
	   $tmp = array();
	   if ($json) {
	       $tmp = json_decode($json, true);
	       $tmp['selector'] = $selector . $newsid;
	       if ($url) {
	           $tmp['apiUrl'] = $url;
	       }
	       if ($selector == 'title') {
	       $tmp['html'] = <<<STR
            <td align="left"  style="vertical-align:bottom;" width="70">
    	       <span class="style5 ui-state-error">原标题</span>
    	    </td>
    	   <td colspan="7" valign="middle">
	   	       <div style="float:left;width:58%;">
	    		<p class="p">
	    		<span id="oldtitle$newsid">
	    		    $content
	    		</span>
	    		</p>
	    	   </div>
	       </td>
STR;
	       } else if ($selector == 'DataUeditor') {
	           if ($extra) {
	               $tmp['translation'][0] = $extra;
	           }
	           $tmp['html'] = $content;
	       }
	   }
	   return json_encode($tmp);
	}
}
