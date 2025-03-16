# CMSFreedom
CMSFreedom was a project launched at [Cloudfest Hackathon 2025](https://hackathon.cloudfest.com/project/cms-freedom/).

The main website is at [CMSFreedom.org](https://cmsfreedom.org)

# What is CMSFreedom?
CMSFreedom is a Chromium browser plugin which can be used to convert static HTML webpages into a functional WordPress block theme. This is created within WordPress Playground and is ready for users to edit, test and export into their own WordPress instance.

Although currently used to create WordPress block themes, it is hoped that as part of the project other CMS platforms can be used.

# How it works (in a nutshell)
When the plugin has been installed for your browser, navigate to the relevant webpage and activate the tool.   
The browser extension will then take multiple screenshots of the webpage, and open a new WordPress playground instance to preprocess the HTML for use by a LLM. 

Once complete, the browser extension will send the screenshots and preproccesed HTML to the LLM to identify the structure of the page.  

The WordPress plugin within the playground instance will then postprocess the response from the LLM and create the strucutre in standard JSON format.

# Process Chart
[Available here](https://github.com/CMSFreedomOrg/CMSFreedom/blob/documentation/doc/process%20chart.md)
