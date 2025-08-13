const functions = require("firebase-functions");
const nodemailer = require("nodemailer");
const { Parser } = require("json2csv");
const admin = require("firebase-admin");
admin.initializeApp();

// Get your Gmail credentials from environment configuration
const gmailEmail = functions.config().gmail.email;
const gmailPassword = functions.config().gmail.password;

// Create a "transporter" to send emails through your Gmail account
const mailTransport = nodemailer.createTransport({
  service: "gmail",
  auth: {
    user: gmailEmail,
    pass: gmailPassword,
  },
});

// Function to send email from the contact form
exports.sendContactEmail = functions.firestore
    .document("artifacts/traders-sanctuary/public/data/contact_messages/{docId}")
    .onCreate((snap, context) => {
      const messageData = snap.data();
      const mailOptions = {
        from: `"Traders Sanctuary" <${gmailEmail}>`,
        to: gmailEmail,
        subject: `New Contact Message from ${messageData.name}`,
        html: `
          <h1>New Message via Contact Form</h1>
          <p><b>Name:</b> ${messageData.name}</p>
          <p><b>Email:</b> ${messageData.email}</p>
          <p><b>Subject:</b> ${messageData.subject}</p>
          <hr>
          <p><b>Message:</b></p>
          <p>${messageData.message}</p>
        `,
      };
      return mailTransport.sendMail(mailOptions);
    });

// Function to export subscribers to a CSV file
exports.exportSubscribersToCSV = functions.https.onCall(async (data, context) => {
  if (context.auth.token.admin !== true) {
    throw new functions.https.HttpsError("permission-denied", "Only admins can export subscribers.");
  }
  const db = admin.firestore();
  const snapshot = await db.collection("artifacts/traders-sanctuary/public/data/subscriptions").get();
  if (snapshot.empty) {
    return { csv: "" };
  }
  const subscribers = snapshot.docs.map(doc => {
      const docData = doc.data();
      return {
          email: docData.email,
          subscribedAt: new Date(docData.subscribedAt).toLocaleString()
      };
  });
  const fields = ["email", "subscribedAt"];
  const parser = new Parser({ fields });
  const csv = parser.parse(subscribers);
  return { csv: csv };
});
// Function to completely delete a user's auth account
exports.deleteUserAccount = functions.https.onCall(async (data, context) => {
  // First, check if the user making the request is an admin
  if (context.auth.token.admin !== true) {
    throw new functions.https.HttpsError(
      "permission-denied",
      "Only admins can delete user accounts."
    );
  }

  const admin = require("firebase-admin");
  const uidToDelete = data.uid;

  if (!uidToDelete) {
     throw new functions.https.HttpsError(
      "invalid-argument",
      "The function must be called with a 'uid' argument."
    );
  }

  try {
    // This deletes the user from Firebase Authentication
    await admin.auth().deleteUser(uidToDelete);
    return { message: `Successfully deleted user ${uidToDelete}` };
  } catch (error) {
    console.error("Error deleting user:", error);
    throw new functions.https.HttpsError(
      "internal",
      "An error occurred while deleting the user."
    );
  }
});
// This function securely grants a user the premium role
exports.grantPremiumAccess = functions.https.onCall(async (data, context) => {
  // Ensure the user calling this function is the one who should get the role
  if (!context.auth || context.auth.uid !== data.userId) {
    throw new functions.https.HttpsError(
      "unauthenticated",
      "You must be logged in to perform this action."
    );
  }

  const admin = require("firebase-admin");
  const db = admin.firestore();
  const userId = data.userId;
  const checkoutId = data.checkoutId;

  try {
    // First, verify that the payment was indeed successful
    const paymentDocRef = db.collection(`artifacts/traders-sanctuary/users/${userId}/payments`).doc(checkoutId);
    const paymentDoc = await paymentDocRef.get();

    if (paymentDoc.exists && paymentDoc.data().status === 'completed') {
      // If payment is verified, update the user's role
      const userProfileRef = db.collection(`artifacts/traders-sanctuary/users/${userId}/profile`).doc('data');
      await userProfileRef.update({
          role: 'premium',
          subscriptionStatus: 'active'
      });
      return { success: true, message: "Account upgraded to Premium!" };
    } else {
      throw new functions.https.HttpsError("not-found", "Payment record not found or not completed.");
    }
  } catch (error) {
    console.error("Error granting premium access:", error);
    throw new functions.https.HttpsError("internal", "An error occurred while upgrading the account.");
  }
});