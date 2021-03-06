<?php

include_once( SEEDCORE."console/console02.php" );
include_once( SEEDLIB."SEEDImg/SEEDImgManLib.php" );

class SEEDAppImgManager
{
    private $oApp;
    private $rootdir;
    private $oIML;

    // controls
    private $currSubdir;
    private $bShowDelLinks;
    private $bShowOnlyIncomplete;

    function __construct( SEEDAppConsole $oApp, $raConfig )
    {
        $this->oApp = $oApp;
        $this->oIML = new SEEDImgManLib( $oApp, $raConfig['imgmanlib'] );

        $this->rootdir = $raConfig['rootdir'];
        $this->currSubdir = $oApp->oC->oSVA->SmartGPC( 'imgman_currSubdir', array() );

        // how do you turn off a checkbox with SmartGPC (unchecked comes back as unset which means use the previous value)
        //$this->bShowDelLinks = $oApp->oC->oSVA->SmartGPC( 'imgman_bShowDelLinks', array(0,1) );
        if( isset($_REQUEST['bControlsSubmitted']) ) {  // this just says that the control form was submitted
            $oApp->oC->oSVA->VarSet( 'imgman_bShowDelLinks', intval(@$_REQUEST['imgman_bShowDelLinks']) );
            $oApp->oC->oSVA->VarSet( 'imgman_bShowOnlyIncomplete', intval(@$_REQUEST['imgman_bShowOnlyIncomplete']) );
        }
        $this->bShowDelLinks = $oApp->oC->oSVA->VarGet( 'imgman_bShowDelLinks' );
        $this->bShowOnlyIncomplete = $oApp->oC->oSVA->VarGet( 'imgman_bShowOnlyIncomplete' );
    }

    function Main()
    {
        $s = "";

        if( ($n = SEEDInput_Str('n')) ) {
            $this->oIML->ShowImg( $this->rootdir.$n );
            exit;   // ShowImg exits anyway but this makes it obvious
        }

        if( ($n = SEEDInput_Str('del')) ) {
            $fullname = $this->rootdir.$n;
            if( file_exists($fullname) ) {
                unlink($fullname);
            }
        }

        $currDir = $this->rootdir.$this->currSubdir;
        if( substr($currDir,-1,1) != '/' ) $currDir .= '/';

        if( ($cmd = SEEDInput_Str('cmd')) ) {
            $raFiles = $this->oIML->AnalyseImages( $this->oIML->GetAllImgInDir( $currDir ) );

            if( $cmd == 'singlekeep' || $cmd == 'singledelete' ) {
                if( !($relbase = SEEDInput_Str('relbase')) )  die( "relbase not specified with cmd $cmd" );
                $searchForFilebase = $this->rootdir.$relbase;
            }
            foreach( $raFiles as $dir => $raF ) {
                foreach( $raF as $filebase => $raFVar ) {
                    if( ($cmd == 'convert' && $raFVar['action'] == 'CONVERT') ||
                        ($cmd == 'multikeep' && SEEDCore_StartsWith( $raFVar['action'], 'KEEP_ORIG' )) ||
                        ($cmd == 'multidelete' && SEEDCore_StartsWith( $raFVar['action'], 'DELETE_ORIG' )) )
                    {
                        $this->oIML->DoAction( $dir, $filebase, $raFVar );
                    }

                    if( ($cmd == 'singlekeep' && SEEDCore_StartsWith($raFVar['action'],'KEEP_ORIG') && $dir.$filebase == $searchForFilebase) ||
                        ($cmd == 'singledelete' && SEEDCore_StartsWith($raFVar['action'],'DELETE_ORIG') && $dir.$filebase == $searchForFilebase) )
                    {
                        $this->oIML->DoAction( $dir, $filebase, $raFVar );
                    }
                }
            }
        }

        // re-run this to get any changes made above
        $raFiles = $this->oIML->AnalyseImages( $this->oIML->GetAllImgInDir( $currDir ) );

        $nActionConvert = $nActionKeep = $nActionDelete = 0;
        /* $raFiles = [dir][filebase] => array( 'exts'=> [ext1 => fileinfo, ext2 => fileinfo, ...], 'action' => ... )
         */
        foreach( $raFiles as $dir => $raF ) {
            foreach( $raF as $filebase => $raFVar ) {
                if( !@$raFVar['action'] )  continue;

                if( $raFVar['action'] == 'CONVERT' ) {
                    $nActionConvert++;
                } else if( SEEDCore_StartsWith( $raFVar['action'], 'KEEP_ORIG' ) ) {
                    $nActionKeep++;
                } else if( SEEDCore_StartsWith( $raFVar['action'], 'DELETE_ORIG' ) ) {
                    $nActionDelete++;
                } else {
                    die( "Unexpected action ".$raFVar['action'] );
                }
            }
        }

        $s .= "<div style='float:right'><form method='post'><input type='hidden' name='bControlsSubmitted' value='1'/>"
                 ."<div><input type='checkbox' name='imgman_bShowDelLinks' value='1' ".($this->bShowDelLinks ? 'checked' : "")."/> Show Del Links</div>"
                 ."<div><input type='checkbox' name='imgman_bShowOnlyIncomplete' value='1' ".($this->bShowOnlyIncomplete ? 'checked' : "")."/> Show Only Incomplete Files</div>"
                 ."<div>"
                     ."<input type='text' name='imgman_currSubdir' id='imgman_currSubdir' value='".SEEDCore_HSC($this->currSubdir)."' size='30'/>"
                     ."<button type='button' id='backbutton'>&lt;-</button>"  // type='button' makes it non-submit
                 ."</div>"
                 ."<div><input type='submit' value='Set Controls'/></div>"
             ."</form></div>";

        if( $nActionConvert )  $s .= "<p><a href='?cmd=convert'>Click here to convert $nActionConvert jpg files to jpeg</a></p>";
        if( $nActionKeep )     $s .= "<p><a href='?cmd=multikeep' style='color:green'>Click here to execute the $nActionKeep <b>Keep</b> links below</a></p>";
        if( $nActionDelete )   $s .= "<p><a href='?cmd=multidelete' style='color:red'>Click here to execute the $nActionDelete <b>Delete</b> links below</a></p>";

        $s .= "<h3>Files under $currDir</h3>";

        $s .= $this->DrawFiles( $raFiles );

        $s .= "<style>#backbutton {}</style>";

        $s .= "<script>
              $(document).ready( function() {
                      $('#backbutton').click( function(e) {
                          e.preventDefault();
                          let v = $('#imgman_currSubdir').val();
                          v = v.match( /^(.*)\/.*\/$/ );
                          $('#imgman_currSubdir').val( v == null ? '' : (v[1]+'/') );
                      });
              });
               </script>";

        $s .= "<script>SEEDCore_CleanBrowserAddress();</script>";

        done:
        return( $s );
    }

    function DrawFiles( $raFiles )
    {
        $s = "<style>#drawfilestable td { padding-right:20px }</style>"
            ."<table id='drawfilestable' style='border:none'>";

        /* $raFiles = array( dir => array( filebase => array( ext1 => fileinfo, ext2 => fileinfo, ...
         */
        foreach( $raFiles as $dir => $raF ) {
            $reldir = substr($dir,strlen($this->rootdir));

            $bDrawDir = true;
            foreach( $raF as $filebase => $raFVar ) {
                $raExts = $raFVar['exts'];
                if( $this->bShowOnlyIncomplete ) {
                    if( count($raExts)==1 && isset($raExts['jpeg']) )  continue;    // don't show files that only have jpeg
                    if( count($raExts)==1 && isset($raExts['gif']) )   continue;    // don't bother showing files that we don't convert
                    if( count($raExts)==1 &&
                        (isset($raExts['png']) || isset($raExts['mp4']) || isset($raExts['webm']) || isset($raExts['webp'])) &&
                        substr($filebase,-7) == 'reduced' )          continue;    // don't show png or mpg files that have been manually reduced
                }

                // this dir has files to show so draw it
                if( $bDrawDir ) {
                    $s .= "<tr><td colspan='5' style='font-weight:bold'><br/><a href='?imgman_currSubdir=".urlencode($reldir)."'>$dir</a></td></tr>";
                    $bDrawDir = false;
                }

                $relfile = $reldir.$filebase;
                $s .= "<tr><td width='30px'>&nbsp;</td>"
                     ."<td style='max-width:150px'>$filebase</td>";
                $infoJpeg = array(); $infoOther = array();
                $sizeJpeg = $sizeOther = $scaleJpeg = $scaleOther = $sizePercent = $scalePercent = 0;
                $sMsg = "";
                $colour = "";
                foreach( $raExts as $ext => $raFileinfo ) {
                    $relfurl = urlencode($relfile.".".$ext);
                    if( $ext == "jpeg" ) {
                        $infoJpeg = $raFileinfo;
                        $sizeJpeg = $raFileinfo['filesize'];
                        $scaleJpeg = $raFileinfo['w'];
                    } else {
                        $infoOther = $raFileinfo;
                        $sizeOther = $raFileinfo['filesize'];
                        $scaleOther = $raFileinfo['w'];
                    }
                    $s .= "<td>"
                             ."<a href='?n=$relfurl' target='_blank'>$ext</a>&nbsp;&nbsp;"
                             .($this->bShowDelLinks ? "<a href='?del=$relfurl' style='color:red'>Del</a>" : "")
                         ."</td>";
                }
                if( count($raExts) == 1 ) {
                    // extra column needed
                    $s .= "<td>&nbsp;</td>";
                }

                // Third column shows scale
                $sScale = "";
                $scaleJpeg = @$raFVar['info']['scaleJpeg'];
                $scaleOther = @$raFVar['info']['scaleOther'];

                if( $scaleJpeg && $scaleOther ) {
                    if( $raFVar['info']['scalePercent'] < 100.0 ) {
                        $sScale = "<span style='color:green'>{$raFVar['info']['sScaleX_Jpeg']}</span> < "
                                 ." <span>{$raFVar['info']['sScaleX_Other']}</span>";
                    } else if( $raFVar['info']['scalePercent'] > 100.0 ) {
                        $sScale = "<span style='color:red'>{$raFVar['info']['sScaleX_Jpeg']}</span> > "
                                 ." <span>{$raFVar['info']['sScaleX_Other']}</span>";
                    } else {
                        $sScale = $raFVar['info']['sScaleX_Jpeg'];
                    }
                } else if( $scaleJpeg ) {
                    $sScale = $raFVar['info']['sScaleX_Jpeg'];
                } else if( $scaleOther ) {
                    $sScale = $raFVar['info']['sScaleX_Other'];
                }
                $s .= "<td style='font-size:8pt'>$sScale</td>";

                // Fourth column shows filesize
                $sSize = "";
                $sizeJpeg = @$raFVar['info']['sizeJpeg'];
                $sizeOther = @$raFVar['info']['sizeOther'];
                $fhJpeg = @$raFVar['info']['filesize_human_Jpeg'];
                $fhOther = @$raFVar['info']['filesize_human_Other'];
                if( $sizeJpeg && $sizeOther ) {
                    $percent = intval($raFVar['info']['sizePercent']);
                    if( $sizeJpeg < $sizeOther ) {
                        $sSize = "<span style='color:green'>$fhJpeg</span> &lt; <span>$fhOther</span> ($percent)%";
                    } else if( $sizeJpeg > $sizeOther ) {
                        $sSize = "<span style='color:red'>$fhJpeg</span> &gt; <span>$fhOther</span> ($percent)%";
                    } else {
                        $sSize = $fhJpeg;
                    }
                } else if( $sizeJpeg ) {
                    $sSize = $fhJpeg;
                } else if( $scaleOther ) {
                    $sSize = $fhOther;
                }
                $s .= "<td style='font-size:8pt'>$sSize</td>";

                // Fifth column shows action
                $relfurl = urlencode($relfile);
                $linkDelJpg = "<b><a href='?cmd=singledelete&relbase=$relfurl' style='color:red'>Delete</a></b>";
                $linkKeepJpg = "<b><a href='?cmd=singlekeep&relbase=$relfurl' style='color:green'>Keep</a></b>";

$fScalePercentThreshold = 90.0;
                $sMsg = "";
                if( $scaleJpeg && $scaleOther && $sizeJpeg && $sizeOther && @$raFVar['action'] ) {
                    list($action,$reason) = explode( ' ', $raFVar['action'] );
                    if( $action == 'DELETE_ORIG' && $reason == 'MAJOR_FILESIZE_REDUCTION' ) {
                        if( $raFVar['info']['scalePercent'] > $fScalePercentThreshold ) {
                            $sMsg = "$linkDelJpg : Filesize reduced a lot with "
                                   .($raFVar['info']['scalePercent'] == 100.0 ? "no" : "<b>minor</b>")
                                   ." loss of scale - delete original JPG";
                            $colour = "orange";
                        } else {
                            $sMsg = " $linkDelJpg : Filesize reduced a lot with significant loss of scale - delete original JPG";
                            $colour = "red";
                        }
                    } else if( $action == 'KEEP_ORIG' && $reason == 'MINOR_FILESIZE_REDUCTION' ) {
                        $sMsg = "$linkKeepJpg : Minor filesize reduction -- keep original JPG";
                        $colour = "green";
                    } else if( $action == 'KEEP_ORIG' && $reason == 'FILESIZE_INCREASE' ) {
                        $sMsg = "$linkKeepJpg : File got bigger -- keep original JPG";
                        $colour = "green";
                    } else if( $action == 'KEEP_ORIG' && $reason == 'FILESIZE_UNCHANGED' ) {
                        $sMsg = " $linkKeepJpg : Filesize not changed -- keep original JPG";
                        $colour = "green";
                    } else {
                        die( "Unexpected action ".$raFVar['action'] );
                    }
                }
                $s .= "<td style='color:$colour'>$sMsg</td>"
                     ."</tr>";
            }
        }

        $s .= "</table>";

        return( $s );
    }
}


function ImgManagerApp( SEEDAppConsole $oApp, $rootdir, $raConfig )
{
    $oImgApp = new SEEDAppImgManager( $oApp, array( 'rootdir'=>$rootdir, 'imgmanlib' => $raConfig['imgmanlib'] ) );

    $raParms = array( "raScriptFiles" => array( W_CORE."js/SEEDCore.js" ) );
    echo Console02Static::HTMLPage( $oImgApp->Main(), "", 'EN', $raParms );   // sCharset defaults to utf8 and filesystem uses utf8
}

?>
