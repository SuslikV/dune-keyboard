<?php

//Notice. As is. It is free.
//
//require_once 'lib/action_factory.php';
//require_once 'lib/control_factory.php';
//require_once 'lib/user_input_handler_registry.php';

class AdvKeyboard implements UserInputHandler
{	
	const ID = 'adv_text_keyboard';
	const enc = 'UTF-8'; //for multibyte strings management

	protected $scrBuff = array(	//screen buffer (12 strings)
		0 => '',
		1 => '',
		2 => '',
		3 => '',
		4 => '',
		5 => '',
		6 => '',
		7 => '',
		8 => '',
		9 => '',
		10 => '',
		11 => ''
	);
	protected $defs = array();		//control definitions (12 labels)
	protected $kLayout = array();	//keys description and layout
	public $curKbrdLayout = 0;		//0, 15, 30, 45, 60, 75, 90... - current keyboard layout/page
									//(5 rows of text + 5 rows of type + 5 rows of commands)
	public $lang = "english";		//keyboard file (loaded language)
	private $shift = false;		//Caps Lock, by default all characters is typed at lower case
	protected $selRow = 0;	//selection row (focus)
	protected $selCol = 0;	//selection column (focus); uses second row of keyboard description (type of keys) as number of horizontal steps
	public $cursPos = 0;	//cursor position in $str
	public $str = "";	//search string
	public $suggestions = array(); //google search suggestions (first 5 will be displayed)
	//test
	//array(
	//	0 => "Hello",
	//	1 => "Hello kitty",
	//	2 => "helloween",
	//	3 => "hellsing",
	//	4 => ""
	//);
	public $suggCountry; //country region for search suggestions
	public $suggLang; //language for for search suggestions
	public $suggSite; //site 
	public $parentHandler;	//parent object user input handler
	public $postActionCtrlId = '';	//action on OK(Find) press
	public $plugin_cookies;
	public $media_url;
	public $btnOkText = ""; //button OK text/name
	public $btnCancelText = ""; //button Cancel text/name
	public $kbrdConfText = ""; //dialog's caption of the setup window
	public $openConfText = ""; //remainder how to open the config window
	public $userKbrds = array( //array of keyboard files that can be loaded via CHANGE command (only a part after the "adv_keyboard_", no extention)
		0 => 'english',
		1 => '-',
		2 => '-'
	);
	public $existingKbrds = array(); //all valid keyboard files from "adv_keyboards" folder (only a part after the "adv_keyboard_", no extention)
	public $firstRun = true;
	public $confOpened = false; //config dialog opened
	
	public function __construct(&$media_url, &$plugin_cookies, $handler, $postActionCtrlId = 'run_search'){
		//hd_print("AdvKeyboard creation");
		
		//to set up actions
		$this->media_url = $media_url;
		$this->plugin_cookies = $plugin_cookies;
		
		//to be able to register post action in caller
		$this->parentHandler = $handler;
		$this->postActionCtrlId = $postActionCtrlId;
		
		UserInputHandlerRegistry::get_instance()->register_handler($this);
    }
	
	public function __destruct(){
		//hd_print("AdvKeyboard destruction");
    }
	
	public function get_handler_id(){
		return self::ID;
	}

///////////////////////////////

	   public function get_action_map($media_url, &$plugin_cookies){
		//hd_print("bind the RC keys");
        $actions = array();
		$actions[GUI_EVENT_KEY_CLEAR] = UserInputHandlerRegistry::create_action($this, 'clear_left');
		$actions[GUI_EVENT_KEY_ENTER] = UserInputHandlerRegistry::create_action($this, 'selection_enter');
		$actions[GUI_EVENT_KEY_UP] = UserInputHandlerRegistry::create_action($this, 'selection_up');
		$actions[GUI_EVENT_KEY_DOWN] = UserInputHandlerRegistry::create_action($this, 'selection_down');
		$actions[GUI_EVENT_KEY_LEFT] = UserInputHandlerRegistry::create_action($this, 'selection_left');
		$actions[GUI_EVENT_KEY_RIGHT] = UserInputHandlerRegistry::create_action($this, 'selection_right');
		$actions[GUI_EVENT_KEY_SELECT] = UserInputHandlerRegistry::create_action($this, 'config_lang');
		$actions[GUI_EVENT_TIMER] = UserInputHandlerRegistry::create_action($this, 'timed_startup');
        return $actions;
    }
	
	public function handle_user_input(&$user_input, &$plugin_cookies){
		if ($user_input->control_id == 'selection_up'){
			//each time adjust Selection according to selected key type (space or not) and screen bounds
			$this->adjust_selection_up();
			return $this->screen_render();
		}
		
		if ($user_input->control_id == 'selection_down'){
			$this->adjust_selection_down();
			return $this->screen_render();
		}
		
		if ($user_input->control_id == 'selection_left'){
			$this->adjust_selection_left();
			return $this->screen_render();
		}
		
		if ($user_input->control_id == 'selection_right'){
			$this->adjust_selection_right();
			return $this->screen_render();
		}
		
		if ($user_input->control_id == 'selection_enter'){
			return $this->selection_enter();
		}
		
		if ($user_input->control_id == 'clear_left'){
			return $this->selection_clear_left();
		}
		
		////// Extention part. It was added later. It uses standard GUI controls and custom events //////
		
		if ($user_input->control_id == 'config_lang'){
			return $this->selection_config_lang(); //should rise dialog with comboboxes to setup some parameters
		}
		
		if ($user_input->control_id == 'set_lang_0'){
			$this->userKbrds[0] = $user_input->set_lang_0;
			HD::save_items('adv_kbrd_lang', $this->userKbrds);
			//hd_print("action set_lang_0");
			return null;
		} 
		
		if ($user_input->control_id == 'set_lang_1'){
			$this->userKbrds[1] = $user_input->set_lang_1;
			HD::save_items('adv_kbrd_lang', $this->userKbrds);
			//hd_print("action set_lang_1");
			return null;
		} 
		
		if ($user_input->control_id == 'set_lang_2'){
			$this->userKbrds[2] = $user_input->set_lang_2;
			HD::save_items('adv_kbrd_lang', $this->userKbrds);
			//hd_print("action set_lang_2");
			return null;
		}
		
		if ($user_input->control_id == 'timed_startup'){
			//hd_print("action timer");
			//just once
			return $this->timed_startup_logic();
				
		}
		
		if ($user_input->control_id == 'close_dialog'){
			//
			//TODO: maybe will use it for something or should remove excessive code
			//
			//hd_print(print_r($user_input, true)); //debug
			if ((isset($user_input->close_config_dialog_kbrd_q21)) &&
					($user_input->close_config_dialog_kbrd_q21)) { //short logic in use
				hd_print("close config dialog (Update keyboard to Language [0])");
				return $this->close_config_dialog();
			} else {
				hd_print("close keyboard dialog (Cancel)");
				return ActionFactory::close_dialog();
			}
		}
		
		if ($user_input->control_id == 'screen_render_after_config'){
			//hd_print("action screen_render_after_config");
			return $this->change_keyboard(0);
				
		}
		
		////// END OF THE: Extention part //////
		
		//catch missing
		hd_print("kbrd: no suitable control_id({$user_input->control_id}) action found");
	}

////////////////////////////  HELPER FUNCTIONS (only simpifies list of the program)
	
	public function screen_render(){
		//highlight selected
		$this->highlight_selected();
		
		//update $scrBuff
		$this->defs = array();
		$this->add_screen_labels();
		
		//refresh the screen
		return $this->screen_refresh();
	}
	
	public function screen_refresh(){
		//hd_print("refresh the screen (apply new defs)");
		return ActionFactory::reset_controls($this->defs);
	}

////////////////////////////  CUSTOM EVENTS PROCESSING

	public function timed_startup_logic(){
		//hd_print("timed startup");
		if ($this->firstRun) {
			hd_print("assuming advanced keyboard first run");
			$this->firstRun = false; //just for beauty of the code
			return $this->selection_config_lang(); //rise the config window where the user can select keyboard languages
		} else {
			//hd_print("not firstRun");
			return $this->change_keyboard(0); //set first available keyboard from the list and render it
		}
	}
	
	public function close_config_dialog(){
		//hd_print("close_config_dialog");
		$this->confOpened = false;
		$post_action = UserInputHandlerRegistry::create_action($this, 'screen_render_after_config');
		return ActionFactory::close_dialog_and_run($post_action);
	}
	
	private function selection_config_lang(){
		hd_print("keyboard languages configuration dialog window");
		$defs = array();
		$exKbrdsAndNone = $this->existingKbrds;
		$exKbrdsAndNone['-'] = '    ---    '; //"none" available for second and third comboboxes only

		//try to load custom settings to use them as start indexes
		$gi = HD::get_items('adv_kbrd_lang');
		if (is_array($gi))
			$this->userKbrds = $gi + $this->userKbrds;
		
		//hd_print(print_r($this->userKbrds, true));

		//no need to confirm_action - all comboboxes may collapse immediately,
		//no matter save action will succeed or not, thus apply_action is used
		ControlFactory::add_combobox(
			$defs,
			$this,
			NULL,
			'set_lang_0',
			'1:',
			$this->userKbrds[0], $this->existingKbrds, 600, false, true
		);
		ControlFactory::add_combobox(
			$defs,
			$this,
			NULL,
			'set_lang_1',
			'2:',
			$this->userKbrds[1], $exKbrdsAndNone, 600, false, true
		);
		ControlFactory::add_combobox(
			$defs,
			$this,
			NULL,
			'set_lang_2',
			'3:',
			$this->userKbrds[2], $exKbrdsAndNone, 600, false, true
		);
		ControlFactory::add_vgap($defs, 30);
		ControlFactory::add_img_label(
			$defs,
			"",
			"<icon>gui_skin://special_icons/controls_button_select.aai</icon><text dy=\"7\" size=\"small\"> - {$this->openConfText}</text>",
			0,
			0,
			0
		);
		//
		//TODO: maybe will use it for something or should remove excessive code
		//
		//'close_config_dialog_kbrd_q21' - needed unique parameter name to not interfere with existing api functions/params
		$this->confOpened = true;
		$add_custom_params = array('close_config_dialog_kbrd_q21' => $this->confOpened);
		$actions[GUI_EVENT_KEY_RETURN] = UserInputHandlerRegistry::create_action($this, 'close_dialog', $add_custom_params);
		//$actions[GUI_EVENT_KEY_STOP] = UserInputHandlerRegistry::create_action($this, 'close_dialog', $add_custom_params);
		$attrs['actions'] = $actions;
		return ActionFactory::show_dialog($this->kbrdConfText, $defs, false, 0, $attrs);
	}

////////////////////////////// KEYS EVENTS PROCESSING

	private function adjust_selection_up(){
		//hd_print("calculate selected position (up)");
	adjUP:
		--$this->selRow;
		if ($this->selRow < 0)
			$this->selRow = 11; //last row - it is always not empty string (OK/Cancel)
		else if (trim(strip_tags($this->scrBuff[$this->selRow])) === "") //if text is empty - skip the row
			goto adjUP; //:)
		//make selected position in bounds if some rows shorter than others
		//move from left-bottom to right-top corner
		//skip the edit field checks
		$this->adjust_selection_left(false);
		$this->adjust_selection_right(false);
	}
	
	private function adjust_selection_down(){
		//hd_print("calculate selected position (down)");
		do {
			++$this->selRow;
			if ($this->selRow > 11)
				$this->selRow = 0;
		} while (trim(strip_tags($this->scrBuff[$this->selRow])) === ""); //if text is empty - skip the row
		//make selected position in bounds if some rows shorter than others
		//move from right-top to left-bottom corner
		//skip the edit field checks
		$this->adjust_selection_right(false);
		$this->adjust_selection_left(false);
	}
	
	private function adjust_selection_left($noskip = true){
		//hd_print("calculate selected position (left)");
		if ($this->selRow < 5) {
			$row = $this->curKbrdLayout + $this->selRow * 3 + 1; //the row of key type from $kLayout
			do {
				--$this->selCol;
				if ($this->selCol < 0)
					$this->selCol = strlen($this->kLayout[$row]) - 1;
			} while ($this->kLayout[$row][$this->selCol] === 's'); //to the last non-space key of the row

		} else if (($this->selRow === 5) && ($noskip)){ //edit field
			//move cursor to the left
			if ($this->cursPos > 0)
				--$this->cursPos;
		} else if ($this->selRow === 11){ //(OK/Cancel)
			--$this->selCol;
			if ($this->selCol < 0)
				$this->selCol = 1;
		} else { //rows 6-10
			$this->selCol = 0;
		}
	}

	private function adjust_selection_right($noskip = true){
		//hd_print("calculate selected position (right)");
		if ($this->selRow < 5) {
			$row = $this->curKbrdLayout + $this->selRow * 3 + 1; //the row of key type from $kLayout
			$maxPos = strlen($this->kLayout[$row]) - 1;
			do {
			++$this->selCol;
			if ($this->selCol > $maxPos)
				$this->selCol = 0;
			} while ($this->kLayout[$row][$this->selCol] === 's'); //to the next non-space key of the row

		} else if (($this->selRow === 5) && ($noskip)){ //edit field
			//move cursor to the right
			if ($this->cursPos < mb_strlen($this->str, self::enc))
				++$this->cursPos;
		} else if ($this->selRow === 11){ //(OK/Cancel)
			++$this->selCol;
			if ($this->selCol > 1)
				$this->selCol = 0;
		} else { //rows 6-10
			$this->selCol = 0;
		}
	}

	private function selection_enter(){
		//hd_print("process the command");

		//use short names
		$selRow = $this->selRow;
		$selCol = $this->selCol;
		$kbrdPage = $this->curKbrdLayout;
		$kLayout = $this->kLayout;

		if ($selRow < 5) {
			//
			//chr(92).chr(32).chr(34).chr(39) -> \ "'
			//
			$keyType = $kLayout[$kbrdPage + $selRow * 3 + 1][$selCol]; //depends on keyboard page(layout)
			//hd_print("keyType = {$keyType}");
			//hd_print("kbrdPage = {$kbrdPage}");
			if ($keyType === 'k') {
				//regular key
				$strWidth = mb_strlen($kLayout[$kbrdPage + $selRow * 3], self::enc); //length in characters;
				$offset = $selCol;
				//while the space at start of the key text...
				while ((($keyEnd = mb_strpos($kLayout[$kbrdPage + $selRow * 3] , " ", $offset, self::enc)) === $offset) &&
						($offset < $strWidth - 1)) {
					++$offset;
				}
				//space position (end of the key text) in characters
				$keyEnd = $keyEnd ? $keyEnd : $strWidth; //to the end of the string
				$keyText = mb_substr($kLayout[$kbrdPage + $selRow * 3], $offset, $keyEnd - $offset, self::enc);
				
				$keyText = ($this->shift) ? mb_strtoupper($keyText, self::enc): mb_strtolower($keyText, self::enc);
				//hd_print("keyText = {$keyText}");
				$this->str = AdvKeyboard::mb_insert_str($this->str, $keyText, $this->cursPos);
				$this->cursPos += mb_strlen($keyText, self::enc); //adjust cursor position after insertion
				//
				// google suggestions (let's us think that it's fast enough)
				//
				$this->suggestions = AdvKeyboard::get_suggestions($this->str, $this->suggCountry, $this->suggLang, $this->suggSite);
				return $this->screen_render();
			} else if ($keyType === 'c') {
				//command key
				return $this->keyCommand();
			} else if ($keyType === 'm') {
				//macro key
				//TODO
				return null;
			} else if ($keyType === 'e') {
				//escape sequence key
				//TODO ?
				return null;
			}
		} else if ($selRow === 5){ //edit field
			if ($this->str === "")
				return null; //do nothing
			//hd_print("process the command close_and_run (from 5)");
			$add_custom_params = array('adv_kbrd_str_to_find_q21' => $this->str);
			$do_search = UserInputHandlerRegistry::create_action($this->parentHandler, $this->postActionCtrlId, $add_custom_params);
			return ActionFactory::close_dialog_and_run($do_search);
		} else if ($selRow === 11){ //(OK/Cancel)
			//post action:     OK key -> close dialog -> find
			//post action: Cancel key -> close dialog -> do nothing
			if ($selCol === 0) {
				//hd_print("process the command close_and_run (from 11)");
				$add_custom_params = array('adv_kbrd_str_to_find_q21' => $this->str);
				$do_search = UserInputHandlerRegistry::create_action($this->parentHandler, $this->postActionCtrlId, $add_custom_params);
				return ActionFactory::close_dialog_and_run($do_search);
			} else {
				//hd_print("process the command close");
				return ActionFactory::close_dialog();
			}
		} else { // 6-10 suggestions
			if (isset($this->suggestions[$selRow - 6])){
				$this->str = $this->suggestions[$selRow - 6]; //copy the suggestion to the string
				$this->cursPos = mb_strlen($this->str, self::enc); //adjust cursor position after copy
				$this->selRow = 5; //move focus to the typed string
				$this->selCol = 0;
				return $this->screen_render();
			}
		}
		hd_print("key type ({$keyType}) not recognized");
		return null;
	}
	
	private function selection_clear_left(){
		//hd_print("delete character left to the cursor");
		//delete the character left to the cursor (from the $str)
		if ($this->cursPos === 0)
			return null; //no action
		else
			$this->str = AdvKeyboard::mb_delete_chr($this->str, --$this->cursPos); //delete and move cursor
		return $this->screen_render();
	}

//////////////////////////// KEYBOARDS FILES LOADING

	public function get_kbrds_list(){
		$kbrdsList = array();

		//all known languages
		$langNames = HD::known_languages(); // ['english'] => 'English'

		foreach(new DirectoryIterator(DuneSystem::$properties['install_dir_path']."/adv_keyboards/") as $file)
			if ($file->isFile() && ($file->getExtension() === 'txt')) { //php 5.3.6 minimum
				$langFileName = $file->getFilename(); //path not included
				$pos = mb_strpos($langFileName, "adv_keyboard_", 0, self::enc);
				if ($pos !== false) {
					$lang = mb_substr($langFileName, $pos + mb_strlen("adv_keyboard_", self::enc), -4, self::enc); // -4 -> exclude extention
					$posCustom = mb_strpos($lang, "_", 0, self::enc); //next underscore
					if ($posCustom === false) {
						$langCasual = $lang;
						$langCustom = "";
					} else {
						$strWidth = mb_strlen($lang, self::enc);
						$langCustom = mb_substr($lang, $posCustom + 1, $strWidth, self::enc); //php v5.3.6 is in use -> mb_strlen() required
						$langCasual = mb_substr($lang, 0, $posCustom, self::enc); //do not include underscore at the end
					}
					//$langFileName = "adv_keyboard_english_fit.txt"
					//        $lang = "english_fit"
					//  $langCustom = "fit"
					//  $langCasual = "english"
				}
				
				//"english_fit" => "English (fit)" pairs
				if (isset($langNames[$langCasual]))
					$kbrdsList[$lang] = ($posCustom === false) ? $langNames[$langCasual] : "{$langNames[$langCasual]} ({$langCustom})";
				else
					$kbrdsList[$lang] = $lang;
				
			};
		//hd_print("Available plugin's advanced keyboards:");
		//hd_print(print_r($kbrdsList, true));
		asort($kbrdsList); //sort by values
		return $kbrdsList;
	}

	//cycle up to 3 keyboards
	private function change_keyboard($j = -1) {
		static $i = 0;
		if ($j !== -1)
			$i = $j - 1;
		do {
			($i < 2) ? ++$i : $i = 0;
			//hd_print("next kbrd file: {$this->userKbrds[$i]}");
		} while (trim($this->userKbrds[$i]) === '-'); //"-" quals to none or skip
		$this->load_keyboard($this->userKbrds[$i]);
		return $this->screen_render();
	}

	public function load_keyboard($lang){
		//hd_print("loading keyboard description file");
		//read from file /adv_keyboards/adv_keyboard_english.txt into array of strings

		//test
		//$this->kLayout = array(
			// 0 => "z w e r t y Enter",
			// 1 => "kskskskskskscssss",
			// 2 => "------------a----",
			// 3 => "Q W E R T Y U I O P BackSpace",
			// 4 => "kskskskskskskskskskscssssssss",
			//5 => "---------------------b--------",
			// 6 => "a s d f G H J \ L ; ' @ / =>|",
			// 7 => "ksksksksksksksksksksksmssksss",
			// 8 => "-----------------------------",
			// 9 => "_Shift  z x c v b n m $ # ? '  ",
			//10 => "cssssssskskskskskskskskskskskss",
			//11 => "f----------------------------",
			//12 => "Esc ~ 1 2 3 4 5 6 7 8 9 0 - +",
			//13 => "csssksksksksksksksksksksksksk",
			//14 => "g----------------------------"
		//);
		
		$filePath = DuneSystem::$properties['install_dir_path']."/adv_keyboards/adv_keyboard_{$lang}.txt";
		$result = true;
		hd_print("Reading keyboard file: {$filePath}");
		if (file_exists($filePath)) {
			$this->kLayout = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		} else {
			$result = false;
			hd_print("Warning: Advanced Keyboard file not found! Trying default one...");
			$filePath = DuneSystem::$properties['install_dir_path']."/adv_keyboards/adv_keyboard_english.txt";
			$this->kLayout = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		}
		if ($this->kLayout === false) {
			$result = false;
			hd_print("ERROR: Fails to load any Advanced Keyboard files!");
			$this->kLayout = array(
				0 => "CLICK_HERE ({$lang})",
				1 => "ksssssssssssssssssssssssssssss",
				2 => "e-----------------------------",
				3 => "ERR: [keyboard file not found]",
				4 => "csssssssssssssssssssssssssssss",
				5 => "------------------------------"
			);
		}

		$this->shift = false; //reset the state of the Caps Lock key
		$this->curKbrdLayout = 0; //reset keyboards page
		
		//hd_print(print_r($this->kLayout, true)); //debug info
		return $result;
	}
////////////////////////////  COMMANDS PROCESSING
	
	private function keyCommand(){
		//hd_print("command key processing");
		
		//use short names
		$selRow = $this->selRow;
		$selCol = $this->selCol;
		$kbrdPage = $this->curKbrdLayout;
		$kLayout = $this->kLayout;
		
		$keyComm = $kLayout[$selRow * 3 + $kbrdPage + 2][$selCol]; //depends on keyboard page(layout)
		//hd_print("keyComm = {$keyComm}");
		if ($keyComm === 'a') {
			//regular key
			//hd_print("process the command close_and_run");
			$add_custom_params = array('adv_kbrd_str_to_find_q21' => $this->str);
			$do_search = UserInputHandlerRegistry::create_action($this->parentHandler, $this->postActionCtrlId, $add_custom_params);
			return ActionFactory::close_dialog_and_run($do_search);
		} else if ($keyComm === 'h') {
			//insert space symbol
			$this->str = AdvKeyboard::mb_insert_str($this->str, ' ', $this->cursPos);
			++$this->cursPos; //adjust cursor position after insertion
			return $this->screen_render();
		} else if ($keyComm === 'b') {
			//backspace
			return $this->selection_clear_left();
		} else if ($keyComm === 'e') {
			//read new keyboard file (cycle the list of 3 keyboards)
			//the command CHANGE key position should be the same for all keyboards and should start at the (0;0)
			return $this->change_keyboard();
		} else if ($keyComm === 'f') {
			//ALT - new keyboard layout/page
			//the key type position should be the same for all layouts (or at least from left-bottom to right-top of the box topology)
			if (($kbrdPage + 15) < count($kLayout))
				$this->curKbrdLayout += 15;
			else 
				$this->curKbrdLayout = 0;
			//make sure that the key is properly selected
			$this->adjust_selection_down();
			$this->adjust_selection_up();
			return $this->screen_render();
		} else if ($keyComm === 'g') {
			//hd_print("process the command close");
			return ActionFactory::close_dialog();
		} else if ($keyComm === 'i') {
			$this->shift = !$this->shift; //toggle
			//the SHIFT key text should has one underscore "_" (lower case) at the start, like: _Shift
			if ($this->shift) {
				$shiftEnd = strpos($kLayout[$kbrdPage + $selRow * 3], '_', $selCol); //look for byte
				if ($shiftEnd !== false)
					$this->kLayout[$kbrdPage + $selRow * 3][$shiftEnd] = '#'; //replace the byte by hash symbol
			} else {
				$shiftEnd = strpos($kLayout[$kbrdPage + $selRow * 3], '#', $selCol); //look for byte
				if ($shiftEnd !== false)
					$this->kLayout[$kbrdPage + $selRow * 3][$shiftEnd] = '_'; //replace the byte by underscore symbol
			}
			return $this->screen_render();
		} else if ($keyComm === 'c') {
			//move cursor to the left
			if ($this->cursPos > 0)
				--$this->cursPos;
			return $this->screen_render();
		} else if ($keyComm === 'd') {
			//move cursor to the right
			if ($this->cursPos < mb_strlen($this->str, self::enc))
				++$this->cursPos;
			return $this->screen_render();
		} else if ($keyComm === 'j') {
			//hd_print("config window");
			return $this->selection_config_lang();
		}
			
		//command key not found
		hd_print("command key ({$keyComm}) not recognized");
		return null;
	}

////////////////////////////  SERVICE FUNCTIONS

	//function to insert string into multibyte string at given position (character)
	//$str - source string;
	//$insStr - string to insert;
	//$insPos - integer, insert position, 0 - means insert before the first symbol of the string:
	//					"abc" ins "_" at 0 -> "_abc"
	//					"abc" ins "_" at 2 -> "ab_c"
	//					"abc" ins "_" at 3 -> "abc_"
	public static function mb_insert_str($str, $insStr, $insPos, $encoding = self::enc){
		$strWidth = mb_strlen($str, $encoding);
		$strStart = mb_substr($str, 0, $insPos, $encoding);
		$strEnd = mb_substr($str, $insPos, $strWidth - $insPos, $encoding);
		return "{$strStart}{$insStr}{$strEnd}";
	}
	
	//function to delete the char from multibyte string at given position (character)
	//$str - source string;
	//$number - integer, number of characters to delete;
	//$delPos - integer, delete position, 0 - means delete from the first symbol of the string;
	public static function mb_delete_chr($str, $delPos, $number = 1, $encoding = self::enc){
		$strWidth = mb_strlen($str, $encoding);
		$strStart = mb_substr($str, 0, $delPos, $encoding);
		$strEnd = mb_substr($str, $delPos + $number, $strWidth - $delPos - $number, $encoding);
		return "{$strStart}{$strEnd}";
	}
	
	//
	//EXPERIMENTAL
	//
	//function to get array of google search suggestions (auto-complete strings)
	//$query - string, part of the search query;
	//$cr - string, 2 letters of ISO 3166 Codes (Countries);
	//$hl - string, 2 letters language code;
	//$ds - string, site specific ('yt' for YouTube);
	public static function get_suggestions($query, $cr = 'us', $hl = 'en', $ds = 'yt'){
		$query = rawurlencode($query);
		
		//no country region -> json
		//$doc = HD::http_get_document("http://suggestqueries.google.com/complete/search?client=firefox&hl={$hl}&ds={$ds}&q={$query}");
		//$sugg = json_decode($doc, true); //array of suggestions is at level 2
		
		//xml with country region
		$doc = HD::http_get_document("http://suggestqueries.google.com/complete/search?client=toolbar&hl={$hl}&gl={$cr}&ds={$ds}&q={$query}");

		//custom xml parse
		$a = explode('<suggestion data="', $doc);
		foreach($a as $value){
			$sugg[] = mb_strstr($value, '"/>', true, self::enc);
		}
		$sugg = array_slice($sugg, 1); //remove first (empty) elementh

		return $sugg;
	}
	
	public function add_screen_labels(){
		//hd_print("draw keyboard screen width: ?? symbols, height: 12 rows");
		$vSpace = -15; //adjust spacing between rows 0-4,6-11

		//keyboard layout:
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[0]);
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[1]);
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[2]);
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[3]);
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[4]);
		//entered string
		ControlFactory::add_smart_label($this->defs, "", $this->scrBuff[5]);
		//google search suggestions, with indentation
		ControlFactory::add_smart_label($this->defs,"", "<text> </text>{$this->scrBuff[6]}"); //NO-BREAK SPACE used &#xa0;
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs,"", "<text> </text>{$this->scrBuff[7]}"); //NO-BREAK SPACE used &#xa0;
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs,"", "<text> </text>{$this->scrBuff[8]}"); //NO-BREAK SPACE used &#xa0;
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs,"", "<text> </text>{$this->scrBuff[9]}"); //NO-BREAK SPACE used &#xa0;
		ControlFactory::add_vgap($this->defs, $vSpace);
		ControlFactory::add_smart_label($this->defs,"", "<text> </text>{$this->scrBuff[10]}"); //NO-BREAK SPACE used &#xa0;
		//buttons OK/Cancel
		ControlFactory::add_smart_label($this->defs,"", $this->scrBuff[11]);
	}
	
	public function highlight_selected(){
		//hd_print("highlight the key");
		//hd_print("sel({$this->selRow};{$this->selCol})");
		//hd_print("cursor({$this->cursPos})");
		//TODO: maybe make colors configurable...
//RGB
//0 = 0x000000 black
//1 = 0x0000A0 dark blue
//2 = 0xC0E0C0 light green
//3 = 0xA0C0FF light blue
//4 = 0xFF4040 bright red
//5 = 0xC0FF40 yellowish green
//6 = 0xFFE040 dark yellow
//7 = 0xC0C0C0 grey 25%
//8 = 0x808080 grey 50%
//9 = 0x4040C0 darker blue
//10 = 0x40FF40 darker green
//11 = 0x40FFFF soft cyan
//12 = 0xFF8040 dark orange
//13 = 0xFF40FF light magenta
//14 = 0xFFFF40 light yellow
//15 = 0xFFFFE0 yellowish white
//Extra:
//16 = 0x404040 grey 75%
//17 = 0xAAAAA0 yellowish grey 33%
//18 = 0xFFFF00 yellow
//19 = 0x50FF50 soft green
//20 = 0x5080FF soft blue
//21 = 0xFF5030 soft red
//23 = 0xE0E0E0 grey 12%
		
		//color blindness safe colors
		$defColor = 15;
		$selColor = 11;
		$searchColor = 6;
		$suggColor = 12;
		$suggSelColor = 11;
		
		$suggestions = $this->suggestions;

		//use short names
		$selRow = $this->selRow;
		$selCol = $this->selCol;
		$kbrdPage = $this->curKbrdLayout;
		$kLayout = $this->kLayout;
		
		$strTmp = "";

		//keyboard layout (5 rows)
		for ($i = 0; $i < 5; ++$i) {
			//hd_print(" i=".$i);
			if ($selRow === $i) {
				$strWidth = mb_strlen($kLayout[$kbrdPage + $i * 3], self::enc); //length in characters;
				$offset = $selCol;
				//while the space at start of the key text...
				while ((($selEnd = mb_strpos($kLayout[$kbrdPage + $i * 3], " ", $offset, self::enc)) === $offset) &&
						($offset < $strWidth - 1)) {
					++$offset;
				}
				//space position (end of the selection) in characters
				$selEnd = $selEnd ? $selEnd : $strWidth; //to the end of the string
				
				$strStart = mb_substr($kLayout[$kbrdPage + $i * 3], 0, $selCol, self::enc); //all characters before selection
				$strSel = mb_substr($kLayout[$kbrdPage + $i * 3], $selCol, $selEnd - $selCol, self::enc);
				$strEnd = mb_substr($kLayout[$kbrdPage + $i * 3], $selEnd + 1, $strWidth, self::enc); //php v5.3.6 is in use -> mb_strlen() required
				$strTmp = "<text color=\"{$defColor}\">{$strStart}</text><text color=\"{$selColor}\">{$strSel} </text><text color=\"{$defColor}\">{$strEnd}</text>";
			} else
				$strTmp = "<text color=\"{$defColor}\">{$kLayout[$kbrdPage + $i * 3]}</text>"; //whole string
			$this->scrBuff[$i] = $strTmp;
			//hd_print($this->scrBuff[$i]);
		}
		
		//typed string (1 row)
		$this->scrBuff[5] = AdvKeyboard::mb_insert_str($this->str, '|', $this->cursPos); //insert cursor sign
		if ($selRow === 5)
			$this->scrBuff[5] = "<text color=\"{$selColor}\">{$this->scrBuff[5]}</text>";
		else
			$this->scrBuff[5] = "<text color=\"{$searchColor}\">{$this->scrBuff[5]}</text>";
		
		//google search suggestions (5 rows)
		for ($i = 0; $i < 5; ++$i) {
			//hd_print(" i=".$i);
			if (isset($suggestions[$i])) {
				if ($selRow === ($i + 6))
					$strTmp = "<text color=\"{$suggSelColor}\">{$suggestions[$i]}</text>";
				else
					$strTmp = "<text color=\"{$suggColor}\">{$suggestions[$i]}</text>";
			} else 
				$strTmp = ""; //empty
			$this->scrBuff[$i + 6] = $strTmp;
			//hd_print($this->scrBuff[$i + 6]);
		}
		
		//buttons OK/Cancel (1 row)
		if ($selRow === 11) {
			if ($selCol === 0)
				$strTmp = "<text color=\"{$selColor}\">{$this->btnOkText}   </text><text color=\"{$defColor}\">{$this->btnCancelText}</text>";
			else
				$strTmp = "<text color=\"{$defColor}\">{$this->btnOkText}   </text><text color=\"{$selColor}\">{$this->btnCancelText}</text>";
		} else 
			$strTmp = "<text color=\"{$defColor}\">{$this->btnOkText}   {$this->btnCancelText}</text>";
		$this->scrBuff[11] = $strTmp;
	}
	
	public function startup_kbrd($caption = 'q2100', $btnOkText = 'OK', $btnCancelText = 'Cancel',
				$kbrdConfText = 'Keyboard languages', $openConfText = 'оpens this window'){
		hd_print("advanced keyboard startup");
		
		$this->btnOkText = $btnOkText;
		$this->btnCancelText = $btnCancelText;
		$this->kbrdConfText = $kbrdConfText;
		$this->openConfText = $openConfText;

		$this->existingKbrds = $this->get_kbrds_list();
		//try to load custom settings
		$gi = HD::get_items('adv_kbrd_lang');
		if ((is_array($gi)) && ($gi !== array())) {
			$this->userKbrds = $gi + $this->userKbrds;
			$this->firstRun = false;
		} else {
			hd_print("no saved language settings");
			HD::save_items('adv_kbrd_lang', $this->userKbrds); //next startup is not first anymore (save defaults)
			$this->firstRun = true;
		}
		
		//prepare the dialog size to default keyboard's height, first draw.
		$this->add_screen_labels();
		
		//rise dialog window
		$attrs['actions'] = $this->get_action_map($this->media_url, $this->plugin_cookies);
		$attrs['timer'] = ActionFactory::timer(1); //delay for 1ms
		$attrs['dialog_params'] = array('frame_style' => DIALOG_FRAME_STYLE_GLASS);
		return ActionFactory::show_dialog($caption, $this->defs, true, 1600, $attrs);
	}
}


?>
