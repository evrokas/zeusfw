Zeus Template System (ZeTem)
Templates have the extension .zetem

TOOD
- Search templates folder recursively to find template name and read contents
- implement filters with the | directive, possibly implement recursive filtering


------------------------------------------------


Hi David,

thanks a lot for this nice template engine.
I made a small change to it that makes it a little more intuitive to use for me.
Now if a block is the first block of a found name, it is replaced with the corresponding yield, otherwise it is simply overwritten and removed from the file.
If a block contains other blocks, it goes through them recursively and also swaps or overwrites the blocks there.
This allows me to make my layout wonderfully structured and readable and once it's abstracted, all I have to do is reference the layout and still create the blocks that need to be replaced.


static function compileBlock($code) {
$matches = [];
preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock \1 ?%}/is', $code, $matches, PREG_SET_ORDER);
foreach ($matches as $value) {
if (!array_key_exists($value[1], self::$blocks)){
self::$blocks[$value[1]] = '';
$code = str_replace($value[0], '{% yield ' . $value[1] . ' %}', $code);
} else {
$code = str_replace($value[0], '', $code);
}
if (strpos($value[2], '@parent') === false) {
self::$blocks[$value[1]] = $value[2];
} else {
self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], $value[2]);
}
if ( strpos(self::$blocks[$value[1]], '{% block') !== false )
self::$blocks[$value[1]] = self::compileBlock(self::$blocks[$value[1]]);
}
return $code;
}

I hope someone other can do something with it too


------------------------------------------------

Hi David,

I am using your class in my personal project and I love it!
I want to share an addon to allow including a view through a variable.
On my site I have to load some modules, whose routes are stored in the database.
I am not sure if this is the best way to do it but it got my code works.

This is how I implemented it:
First we need to add a new property ($data) tho the class
Then we will add a new method (findVars) to process the content of $code
This method gonna do three things:
find the variables inside $code
merge them with the arguments passed to the view
replace the variable in the include with its value

Usage

{% $module= "path/to/$dynamic" %}

{% include $module %}

{% foreach($modules as $module): %}
{% include $module %}
{% endforeach; %}

Where $modules can be a value fetched from the database, or initialized within the view with a dynamic value.

The code should look something like this:	

class Template {

+ static $data = array();
static $blocks = array();
[...]

static function view($file, $data = array()) {
$cached_file = self::cache($file);
extract($data, EXTR_SKIP);
+ self::$data = $data
require $cached_file;
}
[...]
static function includeFiles($file) {

$code = file_get_contents($file);

+ $code = static::findVars($code);
[...]
}

+ static function findVariables($code, $allowed = '/(\html|\php)$/i') {

preg_match_all('/\$([\S]+)(\s*=\s*)([\S]+)/', $code, $matches);

if (empty($matches[0])) {
return $code;
}

$matches = array_combine(str_replace(['"',"'"],['',''], $matches[1]), str_replace(['"',"'"],['',''], $matches[3]));

self::$data = array_merge(self::$data, $matches);
$matches = [];

if (self::$data) {

foreach(self::$data as $key => $value) {

$ext = pathinfo($value)['extension'];

if (preg_match($allowed, $ext)) {
$code = preg_replace('/{% ?(extends|include) ?\$' . $key . ' ?%}/i', '{% include ' . $value . ' %}', $code);
}
}
}

return $code;

}

[...]

What do you think? How would you solve it?

Cheers,

Ludwig



-------------------------------------

IS it possitble to use variables in {% extends ... %} or {% includes... %}? Because I don't use Routes, I call PHP files from different directories, where I load the templates. Now when I use {% include %} inside a loaded Template, the paths are all relative to the PHP file where the Template file is called via the Template::view method (in most cases out of the cache folder), not to the Template file where the include/extend is called. So i would need something like {% extends $root . '/templates/listing/card.html' %}. It seems like the variable are not processed but seen as a regular string.

Do you have an idea how to solve this problem?

Thx!!!!!!



-------------------------------------
Hi, Thanks for this simple but Great and lightweight template engine !
just one question : how can I add partial view ? just like include fucntion but witn an array of data as args
ex : include( string $file, array $data).
?
thanks again
Edit:
Ok, I got it. Just pass an instance of Template itself in the view from controller.
From the controller :
Template::view('index.html',['render' => Template()]);
and then in the view just :
{{ $render::view('partial_view.html',['var'=>'some_arg']) }}

-------------------------------------

Just 2 things I find a bit unfortunate :
First, to use block inheritance(i.e @parent) from the layout.html, I have to use yield and then include the desired "yielded" block from another file and only after that, this block from the layout can be reused in other templates....
a bit long and requires to create lot of files.

Secondly, no vscdoe extension ! I think I will have to make my own to have syntax color and some snippets !
That's said, I found it Very simple and easy tu use but powerfull template engine ! Thanks again.


-------------------------------------
i,thanks for this code
How to add if conditions to this class?

0
Reply
•
Share ›

        −
    Avatar
    David Adams Mod reza sedighi 4 years ago

    You can just do it like the following:

    {% if($condition): %}
    // something here
    {% else: %}
    // something else here
    {% endif; %}
    
-------------------------------------
