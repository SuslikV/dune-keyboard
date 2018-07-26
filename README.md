# dune-keyboard
Dune HD TV onscreen text keyboard new class

# keyboard file description
advanced keyboard file naming:
`adv_keyboard_LANGUAGE_CUSTOMPARTOFTHENAMING.txt`

coding: Linux(LF), UTF-8

Do not skip the rows!

- 1st string is row of key Text.
- 2nd string is row of key Type.
- 3rd string is row of key Command.

Keyboard page consist of 15 rows, all subsequent rows will split by 15 rows per layout (page).
The selection logic uses each 3rd row:
```
Text (0;0) -> row 0, column 0;
     (2;0) -> row 6, column 0;

Type (0;0) -> row 1, column 0;
     (2;0) -> row 7, column 0;

Command (0;0) -> row 2, column 0;
        (2;0) -> row 8, column 0;
```
The selection logic uses left-bottom to top-right rule to move UP and top-right to left-bottom to move DOWN:
```
(row 1)--X--X--
(row 4)-X---X--

Example 1.
  selected (4;1)
  move UP
  selected (1;2)

Example 2.
  selected (4;5)
  move UP
  selected (1;5)

Example 3.
  selected (1;2)
  move DOWN
  selected (4;1)

Example 4.
  selected (1;5)
  move DOWN
  selected (4;5)

Example 5.
  selected (4;1)
  move UP
  selected (1;5)
```
The key Text (word) starts from the column of the selection position, leading spaces ignored, ends at the first space after non-space symbol

`"   name " --> "name"`

`"name    " --> "name"`

`"name"     --> "name"`

The Command CHANGE should be at the the same position for all keyboards and should start at the selection(0;0)

The Text of the SHIFT command should start from the underscore `_` symbol, like `_Shift`.

Keyboard file, keyboard keys array (15 rows is max per layout, all subsequent rows will split by 15 rows per layout), example:
```
q w e r t y Enter
kskskskskskscssss
------------a----
Q W E R T Y U I O P BackSpace
kskskskskskskskskskscssssssss
--------------------b--------
a s d f G H J K L ; - $  =   /
kskskskskskskskskskskskssksssk
------------------------------
Shift z x c v b n m < > ? '
cssssskskskskskskskskskskskss
f----------------------------
Esc ~ 1 2 3 4 5 6 7 8 9 0 - +
csssksksksksksksksksksksksksk
g----------------------------
```
In details:
```
======================================================
keyboard keys description:
======================================================

row 0 description (Text):

key value (symbol/text)
======================================================

row 1 description (Type):

'', " "(empty or space U+0020 [HTML &#32;]) - space between keys (not a key)
k - regular key
s - space between keys (not a key)
m - *macro key (key consist of number of symbols)
c - command key (ENTER, SHIFT, CHANGE layout/language)
e - *escape sequence symbols or special symbols ("\", "&" , "<", ">" etc.), the key number stored in row of key Command.

------------------------------------------------------
*not implemented yet subject to change
======================================================

row 2 description (Command):

- (minus) - command SKIP (no command); to display layout name use (row 0 => 'LayoutName', row 1 => 's', row 2 => '-');
a - command ENTER (do search, output string has value)
b - command BACKSPACE
c - command CURSOR LEFT
d - command CURSOR RIGHT
e - command CHANGE (layout/language or in other words - read new keyboard file)
f - command ALT (at least, should change "key value" for regular keys)
g - command CANCEL (close without search, output string is empty)
h - command SPACE
i - command SHIFT (change register)
j - command CONFIG (rises internal setings window)
k - reserved
======================================================
```
