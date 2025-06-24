package com.daaproject.demo.Controller;

import org.springframework.stereotype.Controller;
import org.springframework.web.bind.annotation.GetMapping;

@Controller
public class MainController {

    @GetMapping("/login")
    public String login() {
        return "login";
    }

    @GetMapping("/welcome")
    public String welcome() {
        return "welcome";
    }
    @GetMapping("/about.html")
    public String aboutPage() {
        return "about";
    }

    @GetMapping("/login.html")
    public String loginPage() {
        return "login";  
    }
    @GetMapping("/book.html")
    public String bookTripPage() {
        return "book"; 
    }
    @GetMapping("/welcome.html")
    public String welcomePage() {
        return "welcome";
    }
}