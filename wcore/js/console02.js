/* Console02.js
 *
 * Copyright (c) 2018 Seeds of Diversity Canada
 * 
 * UI support for Console
 */

class ConsolePage
{
    constructor( config )
    {
        this.cpConfig = config;
        this.cpVars = {};
        this.cpPage = 'Start';

        // Run fnPre for initial page
        this.cpConfig.pages[this.cpPage]['fnPre']();

        // Show the initial page
        this.ShowPage( this.cpPage );
    }
    
    GetVar( k )  	{ return( this.cpVars[k] ); }
    SetVarX( k, v )  { this.cpVars[k] = v; }

    FormVal( k )
    /***********
        Get the current value of the input k on page p
     */
    {
        return( this._formVal( this.cpPage, k ) );
    }
    
    _formVal( p, k )
    /**************
     */
    {
        return( $('#consolePage'+p+' .cpvar_'+k).val() );
    }
    
    LoadVars( p )
    /************
        Populate variable values in all .cpvar_* elements in the given page
     */
    {
        for( let k in this.cpVars ) {
            let e = $('#consolePage'+p+' .cpvar_'+k);
            if( e.length ) {
                if( $.inArray( e.prop('tagName'), ['INPUT','SELECT','TEXTAREA'] ) != -1 ) {
                    e.val( this.GetVar(k) );
                } else {
                    e.html( this.GetVar(k) );
                }
            }
        }
    }
    
    StoreVars( p )
    /*************
        Find all cpvar_* input values in the given page and copy them to cpVars
     */
    {
        let oCP = this;    // because 'this' means something else within the closure
        let page = $('#consolePage'+p);
        page.find('select, textarea, input').each( function() {
            let clist = this.className.split(' ');
            for( let i in clist ) {
                let c = clist[i];
                if( c.substring(0,6) == 'cpvar_' ) {
                    c = c.substring(6);
                    oCP.SetVarX( c, $(this).val() );
                }
            }
        });
    }
    
    ShowPage( p )
    /************
        Show page p and hide all the others
     */
    {
        for( let i in this.cpConfig.pages ) {
            if( i == p ) {
                $('#consolePage'+i).show();
            } else {
                $('#consolePage'+i).hide();
            }
        }
    }

    PageSubmit()
    /***********
        When a submit button is clicked on a page, capture form data, validate it, and decide which page should become current.
     */
    {
        // Run fnPost for the submitted page. The return value is the page that should become current.
        let nextPage = this.cpConfig.pages[this.cpPage]['fnPost']();

        if( nextPage == '' ) {
            // Stay on the same page and don't load vars
        } else {
            // switch to the given next page, after possibly storing the vars of the submitted page
            if( this.cpConfig.pages[this.cpPage]['model'] == 'LoadStore' ) {
                this.StoreVars(this.cpPage);
            }

            this.cpPage = nextPage;

            if( this.cpConfig.pages[this.cpPage]['model'] == 'LoadStore' ) {
                this.LoadVars(this.cpPage);
            }
            // Run fnPre for the new current page
            this.cpConfig.pages[this.cpPage]['fnPre']();
            
            // Show the current page
            this.ShowPage( this.cpPage );
        }
    }
    
    Ready()
    {
        let oCP = this;    // because 'this' means something else within the closure 
        $(document).ready( function () { $('.consolePage form').submit( function (e) { e.preventDefault(); oCP.PageSubmit(); } ); });;
    }
}

