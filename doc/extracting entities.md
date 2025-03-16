# Extracting Entities
The initial process involves extracting the relevant entities from the website - these are then reused as part of the process later. 

The LLM loops over the website several times (maximum 10 times) or until it reaches a `<STOP>` code. 

This requires the input URL to pull this information, which the browser extension does automatically.

# Entity listings
Each of the website entities are processed and stored in their own `.json` file within a folder. The format of these is shown in the below example.

**Example**  
For a `post` entity type (such as a blog post), the system will create a folder `/post/` and inside create a file for each of the relevant pages.

- `my-title.json`
- `second-title.json`

With the following content:
```json
{
  "title": "My title",
 "author": "Steve",
 "content": "My post is amazing.",
 "creation_date": "03/04/2024",
}
