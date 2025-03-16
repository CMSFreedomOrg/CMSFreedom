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
```mermaid
graph TD
    UserNavigatesWebsite[User Navigates to Website] --> CheckExtensionButtonVisibility{Chrome Extension Button Visible?};
    CheckExtensionButtonVisibility -- Yes --> UserClicksConversionButton[User Clicks Conversion Button];
    CheckExtensionButtonVisibility -- No --> UserNavigatesWebsite;
    UserClicksConversionButton --> ShowLoadingButton[Show Loading Button];
    ShowLoadingButton --> CaptureVisualsAndProcessHTML{Capture Visuals and Process HTML};
    CaptureVisualsAndProcessHTML --> LaunchWordPressPlayground[Launch WordPress Playground Instance];
    LaunchWordPressPlayground --> FetchPageHTML[Fetch HTML of Page];
    FetchPageHTML --> PreprocessHTMLForLLM["Preprocess HTML for LLM (Plugin)"];
    PreprocessHTMLForLLM --> CapturePageScreenshots[Take Multiple Screenshots];
    CapturePageScreenshots --> SendDataToLLM[Send Screenshots and Preprocessed HTML to LLM];
    SendDataToLLM --> ReceiveLLMPageStructure[Receive Page Structure from LLM];
    ReceiveLLMPageStructure --> PostprocessLLMResponse["Postprocess LLM Response to JSON (Plugin)"];
    PostprocessLLMResponse --> QueryLLMForGeneratingBlock[Generate WordPress Block Theme with LLM];
    QueryLLMForGeneratingBlock --> GenerateThemeFiles["Generate theme files on WordPress Website (Playground)"];
    GenerateThemeFiles --> OpenNewTabWithWordPress[Open New Tab with WordPress Website];
    OpenNewTabWithWordPress --> HideLoadingButton[Hide Loading Button];
    HideLoadingButton --> ProcessComplete[End];

    subgraph TechnicalDetails
        LaunchWordPressPlayground;
        FetchPageHTML;
        PreprocessHTMLForLLM;
        CapturePageScreenshots;
        SendDataToLLM;
        QueryLLMForGeneratingBlock;
        GenerateThemeFiles;
        ReceiveLLMPageStructure;
        PostprocessLLMResponse;
    end
```

