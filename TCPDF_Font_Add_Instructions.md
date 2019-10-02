# Adding Fonts to TCPDF Laravel Wrapper

## Find or Generate Font and PHP Source Files

For a font to be used and referenced by `TCPDF` as well as your Laravel project simply
gather the `.ttf` and you can use an <a href="http://fonts.snm-portal.com/" target="_blank">online resource</a>
or use the 

```
$ php tcpdf_addfont.php -i [your-font.tff]
```

command with the **font-file** to generate the necessary `*.php`, `*.z` and `.ctg.z` 
files needed for Laravel to remember and render the font.

## Using New Fonts with TCPDF

Once you have your files ready simply copy and paste them into the 
`/vendor/tecnickcom/fonts/` directory.

With that last step all that is needed to do is have `TCPDF` render the font.

## Rendering New Fonts

A new font can be rendered in the PDF with HTML

```
    $html_content = '<h1>Generate a PDF using TCPDF in laravel </h1>
                  <h4 style="font-size: 13px; font-family: ocra; text-align:center;" >by<br/>Learn 2500 0001</h4>';
    PDF::SetTitle('Sample PDF');
    PDF::AddPage();
    //PDF::SetFont('ocra','',10);
    PDF::writeHTML($html_content, true, false, true, false, 'L');
```

or for the whole page using

```
PDF::SetFont('Font-name','','font-size');
```

### Notes

Included is one of the new font file `.tff` as well as the three other files needed
by Laravel (`.z`,`.ctg.z`,`.php`) for the Font-Family `OCRA`. 

They are located in the `font_src` directory