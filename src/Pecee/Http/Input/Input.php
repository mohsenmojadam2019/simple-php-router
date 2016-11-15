<?php
namespace Pecee\Http\Input;

use Pecee\Http\Request;

class Input {

    /**
     * @var \Pecee\Http\Input\InputCollection
     */
    public $get;

    /**
     * @var \Pecee\Http\Input\InputCollection
     */
    public $post;

    /**
     * @var \Pecee\Http\Input\InputCollection
     */
    public $file;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(Request $request) {
        $this->request = $request;
        $this->setGet();
        $this->setPost();
        $this->setFile();
    }

    /**
     * Get all get/post items
     * @param array|null $filter Only take items in filter
     * @return array
     */
    public function all(array $filter = null) {

        $output = $_POST;

        if($this->request->getMethod() === 'post') {

            $contents = file_get_contents('php://input');

            if (stripos(trim($contents), '{') === 0) {
                $output = json_decode($contents, true);
                if($output === false) {
                    $output = array();
                }
            }
        }

        $output = array_merge($_GET, $output);

        if($filter !== null) {
            $output = array_filter($output, function ($key) use ($filter) {
                if (in_array($key, $filter)) {
                    return true;
                }

                return false;
            }, ARRAY_FILTER_USE_KEY);
        }

        return $output;
    }

    public function getObject($index, $default = null) {
        $key = (strpos($index, '[') > -1) ? substr($index, strpos($index, '[')+1, strpos($index, ']') - strlen($index)) : null;
        $index = (strpos($index, '[') > -1) ? substr($index, 0, strpos($index, '[')) : $index;

        $element = $this->get->findFirst($index);

        if($element !== null) {
            return ($key !== null) ? $element[$key] : $element;
        }

        if($this->request->getMethod() !== 'get') {

            $element = $this->post->findFirst($index);

            if ($element !== null) {
                return ($key !== null) ? $element[$key] : $element;
            }

            $element = $this->file->findFirst($index);
            if ($element !== null) {
                return ($key !== null) ? $element[$key] : $element;
            }
        }

        return $default;
    }

    /**
     * Get input element value matching index
     * @param string $index
     * @param string|null $default
     * @return string|null
     */
    public function get($index, $default = null) {

        $item = $this->getObject($index);

        if($item !== null) {

            if($item instanceof InputCollection || $item instanceof InputFile) {
                return $item;
            }

            return (trim($item->getValue()) === '') ? $default : $item->getValue();
        }

        return $default;
    }

    public function exists($index) {
        return ($this->getObject($index) !== null);
    }

    public function setGet() {
        $this->get = new InputCollection();

        if(count($_GET)) {
            foreach($_GET as $key => $get) {
                if(!is_array($get)) {
                    $this->get->{$key} = new InputItem($key, $get);
                    continue;
                }

                $output = new InputCollection();

                foreach($get as $k => $g) {
                    $output->{$k} = new InputItem($k, $g);
                }

                $this->get->{$key} = $output;
            }
        }
    }

    public function setPost() {
        $this->post = new InputCollection();

        $postVars = $_POST;

        if(in_array($this->request->getMethod(), ['put', 'patch', 'delete'])) {
            parse_str(file_get_contents('php://input'), $postVars);
        }

        if(count($postVars)) {

            foreach($postVars as $key => $post) {
                if(!is_array($post)) {
                    $this->post->{strtolower($key)} = new InputItem($key, $post);
                    continue;
                }

                $output = new InputCollection();

                foreach($post as $k => $p) {
                    $output->{$k} = new InputItem($k, $p);
                }

                $this->post->{strtolower($key)} = $output;
            }
        }
    }

    public function setFile() {
        $this->file = new InputCollection();

        if(count($_FILES)) {
            foreach($_FILES as $key => $value) {
                // Multiple files
                if(!is_array($value['name'])) {
                    // Strip empty values
                    if($value['error'] != '4') {
                        $file = new InputFile($key);
                        $file->setName($value['name']);
                        $file->setSize($value['size']);
                        $file->setType($value['type']);
                        $file->setTmpName($value['tmp_name']);
                        $file->setError($value['error']);
                        $this->file->{strtolower($key)} = $file;
                    }
                    continue;
                }

                $output = new InputCollection();

                foreach($value['name'] as $k=>$val) {
                    // Strip empty values
                    if($value['error'][$k] != '4') {
                        $file = new InputFile($k);
                        $file->setName($value['name'][$k]);
                        $file->setSize($value['size'][$k]);
                        $file->setType($value['type'][$k]);
                        $file->setTmpName($value['tmp_name'][$k]);
                        $file->setError($value['error'][$k]);
                        $output->{$k} = $file;
                    }
                }

                $this->file->{strtolower($key)} = $output;
            }
        }
    }

}