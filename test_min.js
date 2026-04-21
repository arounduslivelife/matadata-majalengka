const { GoogleGenerativeAI } = require("@google/generative-ai");
const genAI = new GoogleGenerativeAI("AIzaSyBucp-d5haxnJIw-lJKqSBa8ivekfANrcU");

async function test() {
    try {
        const model = genAI.getGenerativeModel({ model: "gemini-3-flash-preview" });
        const result = await model.generateContent("test");
        console.log(result.response.text());
    } catch (e) {
        console.error("Error:", e.message);
    }
}
test();
