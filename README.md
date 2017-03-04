# Flex Directory Plugin

## About

The **Flex Directory** Plugin is for [Grav CMS](http://github.com/getgrav/grav).  It provides a simple plugin that 'out-of-the-box' acts as a simple user directory.  This plugin allows for CRUD operations via the admin plugin to easily manage large sets of data that don't fit as simple YAML configuration files, or Grav pages.  The example plugin comes with a dummy database of 500 entries which is a realistic real-world data set that you can experiment with.

## Installation

Typically a plugin should be installed via [GPM](http://learn.getgrav.org/advanced/grav-gpm) (Grav Package Manager):

```
$ bin/gpm install flex-directory
```

Alternatively it can be installed via the [Admin Plugin](http://learn.getgrav.org/admin-panel/plugins)

## Sample Data

Once installed you can either create entries manually, or you can copy the sample data set:

```
$ cp user/plugins/flex-directory/data/entries.json user/data/flex-directory/entries.json
```

## Configuration

This plugin really has no configuration except for the ability to enable/disable it:

```
enabled: true
```

## Displaying

To display the directory simply add the following to our Twig template or even your page content (with Twig processing enabled):

```
{% include 'partials/flex-directory-cols.html.twig' %}
```

Alternatively just create a page called `flex-template.md` or set the template of your existing page to `template: flex-template`.  This will use the `flex-template.html.twig` file provided by the plugin.  If this doesn't suit your needs.  You can copy the provided Twig templates into your theme and modify them:


```
flex-directory/templates
├── flex-directory.html.twig
└── partials
    └── flex-directory-cols.html.twig
```

# Modifications

This plugin is configured with a few sample fields:

* published
* first_name
* last_name
* email
* website
* tags

These are probably not the exact fields you might want, so you will probably want to change them. This is pretty simple to do with Flex Directory, you just need to change the **Blueprints** and the **Twig Templates**.  This can be achieved simply enough by copying some current files and modifying them.

Let's assume you simply want to add a new "Phone Number" field to the existing Data and remove the "Tags".  These are the steps you would need to perform:

1. Copy the `blueprints/entries.yaml` Blueprint file to another location, let's say `user/data/flex-directory/` but really it could be anywhere (another plugin, your theme, etc.)
2. Edit the `user/data/flex-directory/entries.yaml` like so:

    ```
    title: Flex Directory
    form:
      validation: loose
    
      fields:
        published:
          type: toggle
          label: Published
          highlight: 1
          default: 1
          options:
            1: PLUGIN_ADMIN.YES
            0: PLUGIN_ADMIN.NO
          validate:
            type: bool
            required: true
        last_name:
          type: text
          label: Second Name
          validate:
            required: true
        first_name:
          type: text
          label: First Name
        email:
          type: email
          label: Email
          validate:
            required: true
        website:
          type: text
          label: Website
        phone:
          type: text
          label: Phone Number
    ```

Notice how we removed the `tags:` Blueprint field definition, and added a simple text field for `phone:`.  If you have questions about available form fields, [check out the extensive documentation](https://learn.getgrav.org/forms/blueprints/fields-available) on the subject.

3. Now we have to instruct the plugin to use this new blueprint rather then the default one provided with the plugin.  This is simple enough, just edit the **Blueprint File** option in the plugin configuration file `flex-directory.yaml` to point to: `user://data/flex-directory/entries.yaml`, and make sure you save it. This will modify the `entries-edit` form automatically.  

4. Now we need to adjust the `entries-list` form that shows the columns.  To do this, you are going to need to copy the existing `user/plugins/flex-directory/admin/templates/partials/entries-list.html.twig` file to another location that is in the **Twig Paths** <sup>1</sup>. The simplest way to add Twig templates is to simply add them under your theme's `templates/` folder ensuring the folder structure is maintained. Let's assume you are using Antimatter theme (although any theme will work), simply copy the `entries-list.html.twig` file to `user/themes/antimatter/admin/templates/partials/entries-list.html.twig` (you will have to create these folders as `admin/` doesn't exist under themes usually) and edit it.

  The first part to edit is the column headers, let's replace the `Tags` header with `Phone`

    ```
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Website</th>
                <th>Phone</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
    ```

  Next you simple need to edit the actual column, replacing the `entry.tags` output with:

    ```
        <td>
            {{ entry.phone }}
        </td>
    ```
    
  This will ensure the backend now lets you edit and list the new "Phone" field, but now we have to fix the frontend to render it.

5. We need to copy the frontend Twig file and modify it to add the new "Phone" field.  By default your theme already has it's `templates`, so we can take advantage of it <sup>2</sup>. We'll simply copy the `user/plugins/flex-directory/templates/partials/flex-directory-cols.html.twig` file to `user/themes/antimatter/templates/partials/partials/flex-directory-cols.html.twig`. Notice, there is no reference to `admin/` here, this is site template, not an admin one.

6. Edit the `flex-directory-cols.html.twig` file you just copied so it has these modifications:

    ```
        <li>
            <div class="entry-details">
                {% if entry.website %}
                    <a href="{{ entry.website }}"><span class="name">{{ entry.last_name }}, {{ entry.first_name }}</span></a>
                {% else %}
                    <span class="name">{{ entry.last_name }}, {{ entry.first_name }}</span>
                {% endif %}
                {% if entry.email %}
                    <p><a href="mailto:{{ entry.email }}" class="email">{{ entry.email }}</a></p>
                {% endif %}
                {% if entry.phone %}
                    <p class="phone">{{ entry.phone }}</p>
                {% endif %}
            </div>
        </li>
    ```

    And also the JavaScript initialization which provides which hooks up certain classes to the search:
    
    ```
    <script>
        var options = {
            valueNames: [ 'name', 'email', 'website', 'phone' ]
        };
    
        var userList = new List('flex-directory', options);
    </script>
    ```


Notice, we removed the `entry-extra` DIV, and added a new `if` block with the Twig code to display the phone number if set.

# Advanced

You can radically alter the structure of the `entries.json` data file by making major edits to the `entries.yaml` blueprint file.  However, it's best to start with an empty `entries.json` if you are making wholesale changes or you will have data conflicts.  Best to create your blueprint first.  Reloading a **New Entry** until the form looks correct, then try saving, and check to make sure the stored `user/data/flex-directory/entries.json` file looks correct.

Then you will need to make more widespread changes to the admin and site Twig templates.  You might need to adjust the number of columns and the field names.  You will also need to pay attention to the JavaScript initialization in each template.

### Notes:

1. You can actually use pretty much any folder under the `user/` folder of Grav. Simply edit the **Extra Admin Twig Path** option in the `flex-directory.yaml` file.  It defaults to `theme://admin/templates` which means it uses the default theme's `admin/templates/` folder if it exists.
2. You can use any path for front end Twig templates also, if you don't want to put them in your theme, you can add an entry in the **Extra Site Twig Path** option of the `flex-directory.yaml` configuration and point to another location.