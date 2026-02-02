Standard ZETEM parser is accepting print commands like: {{ $regions['header'] }}

I want to implement {{ $regions.heder }}

Implement {% set $var = "text" %} command for assigning text, numbers, arrays, etc...

Put all variables in the $variable_context[]

Add {% for $item in $items %} notation to simplify foreach() loops
Track loop-hierarchy with variable $index . Use dot notation $index.0 for current loop,
    $index.1 for parent loop, etc...

Add support for macro . macro will be a function in ZETEM. It acts just like a function.
Notation can be {% macro macro_name(arg1, arg2, ...) %} with until {% endmacro %}. Then code
can call macro just like another function.

Care should be taken, that macro arguments shouldn't be placed inside the $variable_context since they
are internal, and they must be correctly resolved within the macro code.
Also, care should be taken when macro arguments are referenced inside for-loop or if-clause not to be referenced
in the $variable_context[].

In every step check that:
- variable output correctly
- filters work
- if-clauses work
- for-loops work
- nested for-loops work
- $index reference is correct for simple and nested for-loops
- variables work when referenced inside functions in {% %} code

- maybe implement while(): endwhile; notation
