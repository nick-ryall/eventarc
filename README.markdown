# Eventarc
 
* Version: 0.1
* Author: Nick Ryall
* Build Date: 2011-02-11
* Requirements: Symphony 2.2

## Purpose

Syncs one more Symphony sections with the Eventarc event management interface - http://www.eventarc.com

## Installation
 
1. Upload the 'eventarc' folder in this archive to your Symphony 'extensions' folder
2. Enable it by selecting "Eventarc" in the list, choose Enable from the with-selected menu, then click Apply
3. Follow the usage instructions religiously.


## Usage

1. Enter your Eventarc username and password on Symphony preferences page.

2. Create a page called 'Eventarc Updater' and attach the the 'Eventarc Updater' event which comes bundled with the extension.

3. Create a section for your events. There are a number of fields that are essential for the syncing to work correctly. These are listed below.

4. Make sure the "Sync this section with Eventarc?" checkbox is selected before saving your section.

### Fields 

Note: These should be named exactly as seen here as the extension looks for fields with predefined prefixes.

#### REQUIRED (All these fields need to be created. Some are simply placeholders for the returned Eventarc data)

* **e_Name** (textfield)(required) : The name/title of the Event
* **e_Start** (date)(required) : The start data/time for the event.
* **e_Stop** (date)(required) : The stop date/time for the event.
* **e_Deadline** (date)(required) : The ticket deadline for the event.
* **e_Status** (checkbox) : Toggles the event as "Active" or "Draft" in Eventarc.
* **g_id** (textfield)(optional) : A placeholder for the user's Eventarc groups.
* **e_id** (textfield)(optional) : A placeholder for the generated Eventarc ID.
* **e_url** (textfield)(optional) : A placeholder for the generated Eventrac URL.

#### OPTIONAL (These fields can be omitted and the syncing will still work as expected.)

* **e_Description** (textfield)(optional): A description of the event.
* **a_add1** (textfield)(optional) : Address line 1 for the event location.
* **a_add2** (textfield)(optional) : Address line 2 for the event location.
* **a_city** (textfield || select)(optional) : City  for the event location.
* **a_state** (textfield || select)(optional) : State for the event location.
* **a_post** (textfield || select)(optional) : Postcode for the event location.
* **a_country** (textfield || select)(optional) : Country for the event location.

## Current Features

* Events created from within Symphony are re-created on Eventarc.
* Updates made to an event through the Eventarc interface are pushed to Symphony.

## Future Features

* Editing Eventarc entries from within Symphony.
* Deleting Eventarc entries from within Symphony.

These features are expected to be available in the next update to the Eventarc API at which time they will be added to this extension.

## Changelog