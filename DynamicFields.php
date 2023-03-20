<?php

class DynamicFields
{

    /**
     * @var string
     */
    private $tableName = "text_variables";
    /**
     * @var string
     */
    private $tableNameLang = "text_variables_lang";

    /**
     *
     */
    public function __construct()
    {
    }

    /**
     * @return true
     */
    public function getAllFieldsAction()
    {
        $data = \g::db()->getAll("SELECT id, type as 'value', name FROM `dynamic_fields`");
        \g::response()->data()->set(['res' => true, 'data' => $data]);
        return true;
    }


    /**
     * @return true
     */
    public function renameFieldsAction()
    {
        $check = \g::db()->getOne("SELECT COUNT(id) FROM `dynamic_fields` WHERE name = ?s AND template = ?s",
            \g::request()->post()->get('new_name'),
            \g::request()->post()->get('template')
        );
        if ($check > 0) {
            \g::response()->data()->set(['res' => false, 'sd' => $check, 'message' => 'Названия полей должны быть уникальными']);
            return true;
        }
        \g::db()->query("UPDATE `dynamic_fields` SET name = ?s WHERE name = ?s AND template = ?s",
            \g::request()->post()->get('new_name'),
            \g::request()->post()->get('name'),
            \g::request()->post()->get('template')
        );

        \g::db()->query("UPDATE `dynamic_fields_list` SET name = ?s WHERE name = ?s AND template = ?s AND ptype = ?s",
            \g::request()->post()->get('new_name'),
            \g::request()->post()->get('name'),
            \g::request()->post()->get('template'),
            \g::request()->post()->get('template')
        );

        \g::response()->data()->set(['res' => true, 'message' => 'Названия полей сохранены']);
        return true;
    }

    /**
     * @param $template
     * @param $ptype
     * @param $id
     * @param $associative
     * @return array
     */
    public function getFields($template, $ptype, $id, $associative = true)
    {
        $lang = \g::stack()->get('lang');
        $fieldList = \g::db()->getAll("SELECT name, type FROM `dynamic_fields` WHERE `template` = ?s", $template);

        $fields = [];
        foreach ($fieldList as $item) {
            if ($item['type'] == 'bool') {
                $fields[$item['name']] = [
                    'value' => 0,
                    'type' => $item['type']
                ];
            } else {
                $fields[$item['name']] = [
                    'value' => '',
                    'type' => $item['type']
                ];
            }
        }
        unset($fieldList);

        $fieldValues = \g::db()->getAll("SELECT name, value, id FROM `dynamic_fields_list` WHERE template = ?s AND pid = ?s AND ptype = ?s",
            $template,
            $id,
            $ptype
        );
        foreach ($fieldValues as $item) {
            $fields[$item['name']]['value'] = $item['value'];
            $fields[$item['name']]['id'] = $item['id'];
        }
        unset($fieldValues);

        $data = [];
        $list_id_data = [];

        foreach ($fields as $key => $item) {
            if ($associative) {
                $data[$key] = [
                    'name' => $key,
                    'type' => $item['type'],
                    'value' => $item['value'],
                    'id' => $item['id'],
                ];
            } else {
                $data[] = [
                    'name' => $key,
                    'type' => $item['type'],
                    'value' => $item['value'],
                    'id' => $item['id'],
                ];
            }
            $list_id_data[] = $item['id'];
        }

        if (\g::ml()->checkModule('Multilingual') && $lang != 'ru') { //  $lang = \g::stack()->get('lang');
            $fieldLang = \g::db()->getAll("SELECT id_dfl, value FROM dynamic_fields_lang WHERE lang = ?s AND id_dfl in(?a)",
                $lang,
                $list_id_data
            );
            $langValList = [];
            foreach ($fieldLang as $lang_item) {
                $langValList[$lang_item['id_dfl']] = $lang_item['value'];
            }

            foreach ($data as &$d_item) {
                if (array_key_exists($d_item['id'], $langValList)) {
                    $d_item['value'] = $langValList[$d_item['id']];
                }
            }
        }

        return $data;
    }

    /**
     * @return true
     */
    public function saveAction()
    {
        $list = \g::request()->post()->get('list');
        $list = json_decode(stripslashes(html_entity_decode($list)), true);

        $test = [];

        foreach ($list as $item) {
            $test[] = $item['name'];
        }
        if (count(array_unique($test)) < count($test)) {
            \g::response()->data()->set(['res' => false, 'message' => 'Названия полей должны быть уникальными']);
            return true;
        } else {
            \g::db()->query('TRUNCATE TABLE dynamic_fields;');
            $values = [];
            foreach ($list as $item) {
                $values[] = \g::db()->parse('(?s , ?s)', trim($item['name']), trim($item['value']));
            }
            $instr = implode(",", $values);
            \g::db()->query('INSERT INTO `dynamic_fields` (`name`, `value`) VALUES ?p', $instr);
            \g::response()->data()->set(['res' => true, 'message' => 'Поля успешно сохранены']);
            return true;
        }
    }

    /**
     * @return true
     */
    public function deleteFieldsAction()
    {
        $template = \g::request()->post()->get('template');
        $name = \g::request()->post()->get('name');

        \g::db()->query("DELETE FROM `dynamic_fields_list` WHERE `template` = ?s AND `name` = ?s",
            $template,
            $name
        );


        \g::db()->query("DELETE FROM `dynamic_fields` WHERE `template` = ?s AND `name` = ?s",
            $template,
            $name
        );

        \g::response()->data()->set(['res' => true, 'message' => 'Поле успешно удаленно']);
        return true;
    }

    /**
     * @return true
     */
    public function addFieldTemplateAction()
    {
        $template = \g::request()->post()->get('template');
        $name = trim(\g::request()->post()->get('name'));
        $type = \g::request()->post()->get('type');

        $check = \g::db()->getOne("SELECT COUNT(id) as 'count' FROM `dynamic_fields` WHERE `template` = ?s AND `name` = ?s", $template, $name);
        if ($check > 0) {
            \g::response()->data()->set(['res' => false, 'message' => 'Придумайте уникаольное название поля']);
            return true;
        }

        \g::db()->query("INSERT INTO `dynamic_fields` (template, name, type) VALUES ( ?s, ?s, ?s)", $template, $name, $type);
        \g::response()->data()->set(['res' => true, 'message' => 'Поле успешно добавленно']);
        return true;
    }

    /**
     * @return true
     */
    public function updateFieldAction()
    {
        $template = \g::request()->post()->get('template');
        $ptype = \g::request()->post()->get('ptype'); // page, product, category
        $id = \g::request()->post()->get('id');
        $list = \g::request()->post()->get('list');
        $copy_control = \g::request()->post()->get('copy_control');
        $lang = \g::stack()->get('lang');

        $list = json_decode(stripslashes(html_entity_decode($list)), true);

        // \g::ml()->checkModule($nameMod)

        // запишем туда новые к шаблону...
        if (\g::ml()->checkModule('Multilingual')) {
            if ($lang == 'ru') {
                foreach ($list as $item) {
                    if ($item['iddfl'] != 'none' && $copy_control != '-1') {
                        $this->updataElemDFL($template, $ptype, $id, $item);
                    } else {
                        $this->addNewDFL($template, $ptype, $id, $item);
                    }
                }
            } else {
                $listId = [];
                foreach ($list as $item) {
                    $listId[] = $item['iddfl'];
                }
                $checkDB = \g::db()->getAll("SELECT id_dfl, id FROM dynamic_fields_lang WHERE lang = ?s AND id in(?a)", $lang, $listId);
                $id_dfl = [];
                foreach ($checkDB as $elem_dlf) {
                    $id_dfl[$elem_dlf['id_dfl']] = $elem_dlf['id'];
                }

                foreach ($list as $item) {
                    if (array_key_exists($item['iddfl'], $id_dfl) && $copy_control != '-1') {
                        \g::db()->query("UPDATE dynamic_fields_lang SET value = ?s WHERE id = ?i",
                            $item['value'],
                            $id_dfl[$item['iddfl']]
                        );
                    } else {
                        if ($item['iddfl'] == 'none') {
                            $item['iddfl'] = $this->addNewDFL($template, $ptype, $id, $item);
                        }

                        \g::db()->query("INSERT INTO dynamic_fields_lang (id_dfl, lang, value) VALUES ( ?i, ?s, ?s)",
                            $item['iddfl'],
                            $lang,
                            $item['value']
                        );
                    }
                }
            }
        } else {
            foreach ($list as $item) {
                if ($item['iddfl'] != 'none' && $copy_control != '-1') {
                    $this->updataElemDFL($template, $ptype, $id, $item);
                } else {
                    $this->addNewDFL($template, $ptype, $id, $item);
                }
            }
        }


        // SELECT pid, value FROM name_bd WHERE lang = ?s AND id in(?a)

        \g::response()->data()->set([
            'res' => true,
            'message' => 'Поля успешно сохранены',
            'quer' => $quer,
        ]);

        return true;
    }

    // Обновим элемент бд dynamic_fields_lang

    /**
     * @param $template
     * @param $ptype
     * @param $id
     * @param $item
     * @return void
     */
    public function updataElemDFL($template, $ptype, $id, $item)
    {
        \g::db()->query("UPDATE `dynamic_fields_list` SET template  = ?s, ptype  = ?s, pid  = ?s, name  = ?s, value  = ?s WHERE id = ?i",
            $template,
            $ptype,
            $id,
            $item['name'],
            trim($item['value']),
            $item['iddfl']
        );
    }

    // Добави новый элемент бд dynamic_fields_lang

    /**
     * @param $template
     * @param $ptype
     * @param $id
     * @param $item
     * @return int
     */
    public function addNewDFL($template, $ptype, $id, $item)
    {
        \g::db()->query("INSERT INTO `dynamic_fields_list` (template, ptype, pid, name, value) VALUES ( ?s, ?s, ?s, ?s, ?s)",
            $template,
            $ptype,
            $id,
            trim($item['name']),
            $item['value']
        );
        return \g::db()->insertId();
    }

    /**
     * @return true
     */
    public function getFieldsAction()
    {
        $template = \g::request()->post()->get('template');
        $ptype = \g::request()->post()->get('ptype'); // page, product, category
        $id = \g::request()->post()->get('id');
        $lang = \g::stack()->get('lang');

        \g::response()->data()->set(['res' => true, 'lang' => $lang, 'data' => $this->getFields($template, $ptype, $id, false)]);
        return true;
    }

    /**
     * @param $numPage
     * @param $searchText
     * @param $allList
     * @return array
     */
    public function getGlobalList($numPage = 1, $searchText = false, $allList = false) {
		$numPage = intval($numPage);
		$countElem = 10;
		$lim = [];
		$lim['start'] = intval(($numPage*$countElem)-$countElem);
		$lim['sum'] = intval($countElem);
        $lang = \g::stack()->get('lang');
		$queryLim = "LIMIT ".$lim['start']." , ".$lim['sum'];
        $retArray = [];
		$langList = [];
        $query = NULL;
		
		if($allList){ $queryLim = ''; }
		
		if($searchText) {
			if(\g::ml()->checkModule('Multilingual') && $lang != 'ru'){
				$query = \g::db()->getAll("
					SELECT 
						name.id, 
						name.name, 
						lang.id_tv, 
						name.description as 'description',
						lang.description as 'lang_description'
					FROM {$this->tableName} name
					LEFT JOIN {$this->tableNameLang} lang ON name.id = lang.id_tv
					WHERE 
						(
							lang = '$lang' OR 
							lang is NULL
						) 
						AND
						(
							name LIKE '%$searchText%' OR 
							name.description LIKE '%$searchText%' OR 
							lang.description LIKE '%$searchText%'
						)
					ORDER BY id DESC");
			} else {
				$query = \g::db()->getAll("SELECT * FROM {$this->tableName} WHERE name LIKE '%$searchText%' OR description LIKE '%$searchText%' ORDER BY id DESC");
			}
		}
		else {
			$query = \g::db()->getAll("SELECT * FROM {$this->tableName} ORDER BY id DESC $queryLim");
		}
		
		// если есть язык не  РУ, то запросим по этому языку данные и вставим их в массивполей
		if (\g::ml()->checkModule('Multilingual') && $lang != 'ru') {
			$querylang = \g::db()->getAll("SELECT * FROM {$this->tableNameLang} WHERE lang = '$lang'");
			
			foreach ($querylang as $elem_lang) {
				$langList[$elem_lang['id_tv']] = ['id' => $elem_lang['id'], 'desc' => $elem_lang['description']];
			}
		}
		
		
		foreach ($query as $elem) {
			if ($langList[$elem['id']]) {
				$elem['description'] = $langList[$elem['id']]['desc'];
				$elem['id_lang'] = $langList[$elem['id']]['id'];
			}
			
			$retArray[] = [
				'id' => $elem['id'],
				'name' => $elem['name'],
				'description' => stripslashes(htmlspecialchars_decode($elem['description'])),
				'id_lang' => $elem['id_lang']
			];
		}

        return $retArray;
    }

    // Вернёт список всех глобальных объектов onRenderTemplateEvent

    /**
     * @return array
     */
    public function getAllDynamicFields(){
		$list = $this->getGlobalList(1,false,true);
        $returnArray = [];
        foreach ($list as $elem) {
            $returnArray[$elem['name']] = $elem['description'];
        }

        return $returnArray;
	} 
	
	// Вернёт список всех пагенируемых объектов onRenderTemplateEvent

    /**
     * @return array
     */
    public function getRenderList()
    {
        $list = $this->getGlobalList();
        $returnArray = [];
        foreach ($list as $elem) {
            $returnArray[$elem['name']] = $elem['description'];
        }

        return $returnArray;
    }

    // Получим список глобальных переменных

    /**
     * @return true
     */
    public function APIgetGlobalListAction(){
		$page = \g::request()->post()->get("page");
		$searchText = \g::request()->post()->get("searchText");
		
		$list = $this->getGlobalList($page,$searchText);
		
		$countTable = \g::db()->getOne("SELECT COUNT(*) FROM {$this->tableName}");
		
		\g::response()->data()->set([
			'res' => true,
			'list' => $list,
			'count' => $countTable,
		]);
		
		return true;
	}
	
	// добавит новую переменную в бд

    /**
     * @return true
     */
    public function APIaddVariablesAction()
    {
        $lang = \g::stack()->get('lang');
        $name = \g::request()->post()->get("name");
        $description = trim(\g::request()->post()->get("description"));

        $regTest = preg_match("/^[0-9A-Za-z_]*$/i", trim(strip_tags($name)));
        if ($regTest) {
            $check_record = \g::db()->getOne("SELECT EXISTS(SELECT name FROM {$this->tableName} WHERE name = '$name')");
            if (!$check_record) {
                $query = \g::db()->query("INSERT INTO {$this->tableName} SET 
					name = '$name' , 
					description = '$description'
				");
                $queryId = \g::db()->insertId();

                if (\g::ml()->checkModule('Multilingual') && $lang != 'ru') {
                    $query = \g::db()->query("INSERT INTO {$this->tableNameLang} SET 
						id_tv = '$queryId' , 
						lang = '$lang' , 
						description = '$description'
					");
                }

                \g::response()->data()->set([
                    'res' => true,
                    'check_record' => $check_record
                ]);
            } else {
                \g::response()->data()->set([
                    'res' => false,
                    'errors' => 'Такая переменная уже есть',
                ]);
            }
        } else {
            \g::response()->data()->set([
                'res' => false,
                'errors' => 'Некорректное имя',
            ]);
        }

        return true;
    }

    // перезаписать значение "description" переменной

    /**
     * @return true
     */
    public function APIchangeVariablesAction()
    {
        $id = \g::request()->post()->get("id");
        $id_lang = \g::request()->post()->get("id_lang");
        $description = \g::request()->post()->get("description");

        if ($this->changeOneVariable($id,$id_lang,$description)) {
            \g::response()->data()->set([
                'res' => true,
                'description' => $description,
                '_POST' => $_POST
            ]);
        } else {
            \g::response()->data()->set([
                'res' => false,
                'errors' => 'delete error',
            ]);
        }
        return true;
    }
	
	// сохранить все "description" что пришли

    /**
     * @return true
     */
    public function APISaveAllVariablesAction(){
		$id_lang = \g::request()->post()->get("id_lang");
		$description = \g::request()->post()->get("description");

		foreach ($description as $key_id => $dataText) {
			$this->changeOneVariable($key_id,$id_lang[$key_id],$dataText);
		}
		
		\g::response()->data()->set([
			'res' => true,
			'description' => $description,
			'id_lang' => $id_lang,
			'_POST' => $_POST
		]);
		return true;
	}

    // удалит из бд переменную

    /**
     * @return true
     */
    public function APIdeleteVariablesAction()
    {
        $id = \g::request()->post()->get("id");

        if ($id) {
            $query = \g::db()->query("DELETE FROM {$this->tableName} WHERE id = '$id'");
            \g::response()->data()->set([
                'res' => true,
            ]);
        } else {
            \g::response()->data()->set([
                'res' => false,
                'errors' => 'delete error',
            ]);
        }

        return true;
    }
	
	// Сохранить одно языковое поле 

    /**
     * @param $id
     * @param $id_lang
     * @param $description
     * @return bool
     */
    private function changeOneVariable($id, $id_lang = 'none', $description = ''){
		if ($id) {
			// if($description) { $description = htmlspecialchars(trim($description)); }
            $lang = \g::stack()->get('lang');

            if (\g::ml()->checkModule('Multilingual') && $lang != 'ru') {
                if ($id_lang != 'none') {
                    $query = \g::db()->query("UPDATE {$this->tableNameLang} SET 
						description = ?s  
						WHERE id = '$id_lang'
					",$description);
                } else {
                    $query = \g::db()->query("INSERT INTO {$this->tableNameLang} SET 
						id_tv = '$id' , 
						lang = '$lang' , 
						description = ?s
					",$description);
                }
            } else {
                $query = \g::db()->query("UPDATE {$this->tableName} SET 
					description = ?s  
					WHERE id = '$id'
				",$description);
            }
           return true;
        } else {
			return false;
        }
	}
}
