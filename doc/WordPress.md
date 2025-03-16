# WordPress Integration
The WordPress Integration pulls the information from the [entities exported from the website](https://github.com/CMSFreedomOrg/CMSFreedom/blob/documentation/doc/extracting%20entities.md) and imports these into WordPress Playground. 

To achieve this, WordPress Playground imports the entities into the database using the [MCP WP-CLI](https://hackathon.cloudfest.com/project/wp-cli-mcp-host/) plugin and imports each native post type using the command `wp ai {post-type} {my data}`




