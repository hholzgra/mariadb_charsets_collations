Every once in a while questions like the one in MySQL Bug #60843 or Bug #19567 come up:

> What collation should i use if i want case insensitive behavior but also want all accented letter to be treated as distinct to their base letters?

or shorter, as the reporter of bug #60843 put it:

> I need something like utf8_bin + ci

`utf8_general_ci` and `utf8_unicode_ci` unfortunately do not provide 
this behavior and `utf8_bin` is obviously not case insensitive.

Language specific collations may provide this for accented letters 
that are acutally part of the languages alphabet, e.g. in `utf8_swedish_ci` 
'`Å`' is a distinct letter but '`Á`' is still treated as equal to '`A`'.

So what is needed to create a collation where all accented forms of the 
26 latin standard letters (or ASCII letters) are treated as distinct letters
while still having case insensitive behavior?

Fortuantely MariaDB and MySQL allows us to add our own collations without 
having to modify the server itself, see Adding Collations and for our case
 esp. Adding a UCA Collation to an Unicode Character Set.

So all we need to do is to create a new set of LDML (Locale Data Markup Language) 
collation rules that basically looks like this:

```xml
      <reset>A</reset>

      <p>\u00c0</p><!-- À -->
      <t>\u00e0</t><!-- à -->

      <p>\u00c1</p><!-- Á -->
      <t>\u00e1</t><!-- á -->

      [...]
      <reset>B</reset>
      [...]
```
 

where all accented upper case forms of a base letter are defined as distinct 
separate letters ('primary' or `<p>`) sorted after that base letter, and the 
lower case form as a only differing by case from the upper case form 
('tertiary' or `<t>`). Should there only be a lower case form of an accented 
letter but no upper case equivalent then this lower case only combination 
needs to be registered as a primary distinct letter instead of a tertiary.

LDML actually specifies that accented variations of a letter are supposed to 
be registered as 'secondary' or <`s`>, but as MariaDB does not distinguish 
between primaries and secondaries we need to register all accented letters as 
primaries instead to get the desired behavior.

A sort order where all accented letters follow their base letter obviously 
doesn't match the sort order of any actual language, and is also different to 
the way `utf8_bin` orders things, but as we only really care about the 
comparison behavior of the collation and not about how things get sorted here 
we should be fine with this. There isn't a catch-all sorting order that suits 
all latin based languages anyway ...

So now that we know how the rule set should look like the question that 
remains is: how to find all valid accented letter combinations and how to 
create a complete rule set from that list quickly.

This can fortunately be automated by using Unicode normalization mechanism. 
Unicode allows to represent accented letters by either a single code point 
or by a combination of a base character and one or more modifiers (even 
though MariaDB only really supports the single code point approach). 
So e.g. '`Á`' can either be the single code point `U+00C1 'LATIN CAPITAL A 
WITH ACUTE'` or the combination of '`A`' (`U+0041 'LATIN CAPITAL A'`) and 
`U+0301 'COMBINING ACUTE ACCENT'`. Unicode normalization allows us to convert 
everything to either the single code point (composed) or code point sequence 
(decomposed) form.

Now we can iterate over the full unicode codepoint range and identify
accented / combined versions of latin base letters by requesting the 
decomposed normalized form and checking whether this starts with one of our 
base letters followed by any combining codepoint. We can then create a list 
of all accented / combined versions of each base letter and sort this by its 
combining modifiers code points and can identify upper and lower case 
combination pairs as they will only differ by their base letter being the 
upper or lower case form of the same letter with the same modifiers applied. 
From this lists we can then create the LDML ruleset needed to add our new 
collation.

There are only two more things left to do: find a good name and an unused 
collation ID for our new collation. For a name I picked `utf8_distinct_ci` 
for now and as collation ID i picked` 252` as the highest ID used so far 
on my MariaDB 5.5 instance was 251.

The following little PHP script performs all this with a little help from 
the Internationalization Extension and the resulting output can be found here. 
The new collation can be activated by adding the generated collation rule set 
to the utf8 charset section within your MySQL installations 
`charsets/Index.xml` file and restarting the mysqld server process.